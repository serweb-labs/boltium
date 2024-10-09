<?php

/*
 * Based in Symfony MakerBundle package.
 *
 * (c) Luciano Rodriguez <luciano.rdz@gmail.com>
 *
 */

namespace App\Maker;

use App\ContentConfig;
use App\Maker\EntityClassGenerator;

use Symfony\Bundle\MakerBundle\ConsoleStyle;
use Symfony\Bundle\MakerBundle\DependencyBuilder;
use Symfony\Bundle\MakerBundle\Doctrine\DoctrineHelper;
use Symfony\Bundle\MakerBundle\Doctrine\EntityRegenerator;
use Symfony\Bundle\MakerBundle\Doctrine\EntityRelation;
use Symfony\Bundle\MakerBundle\Doctrine\ORMDependencyBuilder;
use Symfony\Bundle\MakerBundle\FileManager;
use Symfony\Bundle\MakerBundle\Generator;
use Symfony\Bundle\MakerBundle\InputAwareMakerInterface;
use Symfony\Bundle\MakerBundle\InputConfiguration;
use Symfony\Bundle\MakerBundle\Util\ClassDetails;
use Symfony\Bundle\MakerBundle\Util\ClassSource\Model\ClassProperty;
use Symfony\Bundle\MakerBundle\Util\ClassSourceManipulator;
use Symfony\Bundle\MakerBundle\Util\CliOutputHelper;
use Symfony\Bundle\MakerBundle\Validator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Question\Question;
use Symfony\Bundle\MakerBundle\Maker\AbstractMaker;


final class MakeContentTypes extends AbstractMaker implements InputAwareMakerInterface
{
    private Generator $generator;
    private EntityClassGenerator $entityClassGenerator;

    private $normalFields = [
        'text',
        'checkbox',
        'number',
        'datetime',
        'date',
        'slug',
        'id',
    ];

    private $relationFields = [
        'collection',
        'set',
        'content',
    ];

    public function __construct(
        private FileManager $fileManager,
        private DoctrineHelper $doctrineHelper,
        private ContentConfig $ctConfig,
        ?Generator $generator = null,
        ?EntityClassGenerator $entityClassGenerator = null,
    ) {

        if (null === $generator) {
            @trigger_error(sprintf('Passing a "%s" instance as 4th argument is mandatory since version 1.5.', Generator::class), \E_USER_DEPRECATED);
            $this->generator = new Generator($fileManager, 'App\\');
        } else {
            $this->generator = $generator;
        }

        if (null === $entityClassGenerator) {
            @trigger_error(sprintf('Passing a "%s" instance as 5th argument is mandatory since version 1.15.1', EntityClassGenerator::class), \E_USER_DEPRECATED);
            $this->entityClassGenerator = new EntityClassGenerator($generator, $this->doctrineHelper);
        } else {
            $this->entityClassGenerator = $entityClassGenerator;
        }
    }


    public static function getCommandName(): string
    {
        return 'make:contenttypes';
    }


    public static function getCommandDescription(): string
    {
        return 'Create or update a Doctrine entity class from the Branapp YAML configuration';
    }


    public function configureCommand(Command $command, InputConfiguration $inputConfig): void
    {
        $command
            ->addArgument('name', InputArgument::OPTIONAL, sprintf('Content Type in contenttentypes.yml to create or update (e.g. <fg=yellow>%s</>)', 'staff'))
            ->addOption('overwrite', null, InputOption::VALUE_NONE, 'Overwrite any existing getter/setter methods')
        ;

        $inputConfig->setArgumentAsNonInteractive('name');
    }


    public function interact(InputInterface $input, ConsoleStyle $io, Command $command): void
    {
        if ($input->getArgument('name')) {
            return;
        }

        $argument = $command->getDefinition()->getArgument('name');
        $question = $this->createEntityClassQuestion($argument->getDescription());
        $contentType = $io->askQuestion($question);

        $input->setArgument('name', $contentType);

    }


    public function generate(InputInterface $input, ConsoleStyle $io, Generator $generator): void
    {
        $overwrite = $input->getOption('overwrite');

        $className = $this->ctConfig->getConfig($input->getArgument('name'))['singular_name'];
        $entityClassDetails = $generator->createClassNameDetails(
            $className,
            'Entity\\'
        );

        $contentType = $this->ctConfig->getConfig($input->getArgument('name'));

        $classExists = class_exists($entityClassDetails->getFullName());

        if ($classExists) {
            $entityPath = $this->getPathOfClass($entityClassDetails->getFullName());
            $io->text([
                'Your entity already exists! So let\'s add some new fields!',
            ]);
        } else {
            $entityPath = $this->entityClassGenerator->generateEntityClass(
                $entityClassDetails,
                false,
                false,
                false,
                false
            );
            $io->text([
                '',
                'Entity generated! Now let\'s add some fields!',
                'You can always add more fields later manually or by re-running this command.',
            ]);
            $generator->writeChanges();
        }

        $currentFields = $this->getPropertyNames($entityClassDetails->getFullName());
        $manipulator = $this->createClassManipulator($entityPath, $io, $overwrite);

        foreach ($contentType['fields'] as $key => $value) {
            $newField = $this->makeNextField(
                $io,
                $currentFields,
                $entityClassDetails->getFullName(),
                $contentType,
                $key
            );

            if (null === $newField) {
                break;
            }

            $fileManagerOperations = [];
            $fileManagerOperations[$entityPath] = $manipulator;

            if ($newField instanceof ClassProperty) {
                $manipulator->addEntityField($newField);
                $currentFields[] = $newField->propertyName;
            } 
            elseif ($newField instanceof EntityRelation) {
                // TODO Relation
            }
            else {
                throw new \Exception('Invalid value');
            }

            foreach ($fileManagerOperations as $path => $manipulatorOrMessage) {
                if (\is_string($manipulatorOrMessage)) {
                    $io->comment($manipulatorOrMessage);
                } else {
                    $this->fileManager->dumpFile($path, $manipulatorOrMessage->getSourceCode());
                }
            }
        }

        $this->writeSuccessMessage($io);
        $io->text([
            sprintf('Next: When you\'re ready, create a migration with <info>%s make:migration</info>', CliOutputHelper::getCommandPrefix()),
            '',
        ]);
    }


    public function configureDependencies(DependencyBuilder $dependencies, ?InputInterface $input = null): void
    {
        ORMDependencyBuilder::buildDependencies($dependencies);
    }


    private function makeNextField(ConsoleStyle $io, array $fields, string $entityClass, $contentType, $key): EntityRelation|ClassProperty|null
    {
        $io->writeln('');

        $field = $contentType['fields'][$key];
        $fieldType = $field['type'];

        if (\in_array($fieldType, $this->normalFields)) {
            return $this->makeNormalField($field);
        }
        elseif (\in_array($fieldType, $this->relationFields)) {
            return $this->makeRelationField($field);
        }

        return null;
    }

    private function createEntityClassQuestion(string $questionText): Question
    {
        $question = new Question($questionText);
        $question->setValidator(Validator::notBlank(...));
        $question->setAutocompleterValues($this->doctrineHelper->getEntitiesForAutocomplete());

        return $question;
    }


    private function createClassManipulator(string $path, ConsoleStyle $io, bool $overwrite): ClassSourceManipulator
    {
        $manipulator = new ClassSourceManipulator(
            sourceCode: $this->fileManager->getFileContents($path),
            overwrite: $overwrite,
        );

        $manipulator->setIo($io);

        return $manipulator;
    }


    private function getPathOfClass(string $class): string
    {
        return (new ClassDetails($class))->getPath();
    }


    private function getPropertyNames(string $class): array
    {
        if (!class_exists($class)) {
            return [];
        }

        $reflClass = new \ReflectionClass($class);

        return array_map(static fn (\ReflectionProperty $prop) => $prop->getName(), $reflClass->getProperties());
    }


    private function makeNormalField($field) {
        $fieldName = $field['name'];
        $fieldType = $field['type'];
        $mode = isset($field['mode']) ? $field['mode'] : null;

        if (!$fieldName) {
            return null;
        }

        $type = 'string';

        if ($fieldType == 'text') {
            $type = 'string';
        } elseif ($fieldType == 'checkbox') {
            $type = 'boolean';
        } elseif ($fieldType == 'number') {
            if ($mode == 'integer') {
                $type = 'integer';
            }
            else {
                $type = 'float';
            }
        } elseif ($fieldType == 'image') {
            $type = 'string';
        } elseif ($fieldType == 'date') {
            $type = 'datetime';
            if ($mode == 'date') {
                $type = 'date';
            }
            elseif ($mode == 'time') {
                $type = 'time';
            }
        } elseif ($fieldType == 'slug') {
            $type = 'string';
        }

        if ($fieldName == 'id') {
            $type = 'string';
        } 

        // this is a normal field
        $classProperty = new ClassProperty(propertyName: $fieldName, type: $type);

        if ('string' === $type) {
            // default to 255, avoid the question
            // TODO
            $classProperty->length = 255;
        } elseif ('decimal' === $type) {
            // 10 is the default value given in \Doctrine\DBAL\Schema\Column::$_precision
            // TODO
            $classProperty->precision = 10;

            // 0 is the default value given in \Doctrine\DBAL\Schema\Column::$_scale
            // TODO
            $classProperty->scale = 0;
        }

        // TODO
        $classProperty->nullable = true;

        return $classProperty;
    }

    private function makeRelationField($field) {
        $fieldName = $field['name'];
        $fieldType = $field['type'];
        $isNullable = !$field['required'];

        $mode = isset($field['mode']) ? $field['mode'] : null;
        
        if (!$fieldName) {
            return null;
        }
        

        if ($fieldType == 'collection') {
            $targetEntityClass = ''; // TODO 
            $relation = new EntityRelation(
                EntityRelation::MANY_TO_ONE,
                $generatedEntityClass,
                $targetEntityClass
            );
            $relation->setMapInverseRelation(true);
        }

        $relation->setOwningProperty($fieldName);

        $relation->setIsNullable($isNullable);

        if ($relation->getMapInverseRelation()) {

            $relation->setInverseProperty($fieldName);

            // orphan removal only applies if the inverse relation is set
            if (!$relation->isNullable()) {
                $relation->setOrphanRemoval(false);
            }
        }

        return $relation;
    }
}