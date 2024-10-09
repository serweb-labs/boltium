<?php

/*
 * This file is part of the Symfony MakerBundle package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Maker;

use App\ContentConfig;
use App\ViewsConfig;
use Twig\Environment;

use ApiPlatform\Metadata\ApiResource;
use Doctrine\DBAL\Types\Type;
use Symfony\Bundle\MakerBundle\ConsoleStyle;
use Symfony\Bundle\MakerBundle\DependencyBuilder;
use Symfony\Bundle\MakerBundle\Doctrine\DoctrineHelper;
use Symfony\Bundle\MakerBundle\Doctrine\EntityClassGenerator;
use Symfony\Bundle\MakerBundle\Doctrine\EntityRegenerator;
use Symfony\Bundle\MakerBundle\Doctrine\EntityRelation;
use Symfony\Bundle\MakerBundle\Doctrine\ORMDependencyBuilder;
use Symfony\Bundle\MakerBundle\Exception\RuntimeCommandException;
use Symfony\Bundle\MakerBundle\FileManager;
use Symfony\Bundle\MakerBundle\Generator;
use Symfony\Bundle\MakerBundle\InputAwareMakerInterface;
use Symfony\Bundle\MakerBundle\InputConfiguration;
use Symfony\Bundle\MakerBundle\Str;
use Symfony\Bundle\MakerBundle\Util\ClassDetails;
use Symfony\Bundle\MakerBundle\Util\ClassSource\Model\ClassProperty;
use Symfony\Bundle\MakerBundle\Util\ClassSourceManipulator;
use Symfony\Bundle\MakerBundle\Util\CliOutputHelper;
use Symfony\Bundle\MakerBundle\Validator;
use Symfony\Bundle\MercureBundle\DependencyInjection\MercureExtension;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Question\Question;
use Symfony\Bundle\MakerBundle\Maker\AbstractMaker;

use Nette\PhpGenerator\ClassType;
use Nette\PhpGenerator\PhpNamespace;

/**
 * @author Javier Eguiluz <javier.eguiluz@gmail.com>
 * @author Ryan Weaver <weaverryan@gmail.com>
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
final class MakeViews extends AbstractMaker implements InputAwareMakerInterface
{
    private Generator $generator;
    private EntityClassGenerator $entityClassGenerator;

    public function __construct(
        private FileManager $fileManager,
        private DoctrineHelper $doctrineHelper,
        private ViewsConfig $viewsConfig,
        private ContentConfig $ctConfig,
        private Environment $twig,
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
        return 'make:views';
    }

    public static function getCommandDescription(): string
    {
        return 'Create or update views from the Branapp YAML configuration';
    }

    public function configureCommand(Command $command, InputConfiguration $inputConfig): void
    {
        $command
            ->addArgument('name', InputArgument::OPTIONAL, sprintf('Content Type in contenttentypes.yml to create or update (e.g. <fg=yellow>%s</>)', 'staff'))
        ;

        $inputConfig->setArgumentAsNonInteractive('name');
    }

    public function interact(InputInterface $input, ConsoleStyle $io, Command $command): void
    {
        if ($input->getArgument('name')) {
            return;
        }

        $input->setArgument('name', 'all');

    }

    public function generate(InputInterface $input, ConsoleStyle $io, Generator $generator): void
    {
        
        $view = $input->getArgument('name');

        // generate twig templates

        if ($view == 'all') {
            foreach ($ct as $key => $value) {
                $this->generateView($key, $io);
                $this->generateController($key, $io);
            }            
        }
        else {
            $this->generateView($view, $io);
            $this->generateController($view, $io);
        }


        $this->writeSuccessMessage($io);
    }


    public function configureDependencies(DependencyBuilder $dependencies, ?InputInterface $input = null): void
    {
        ORMDependencyBuilder::buildDependencies($dependencies);
    }

    private function generateView(string $viewName, $io) {

        $viewConfig = $this->viewsConfig->getConfig($viewName);

        $this->generator->generateTemplate(
            sprintf('views/%s.html.twig', $viewName),
            __dir__.'/view_twig_template.tpl.php',
            [
                'content' => $this->getContentTpl($viewConfig),
            ]
        );

        $this->generator->writeChanges();

        $io->text([
            sprintf('Generated view: <info>%s</info>', $viewName),
            '',
        ]);

    }


    private function getContentTpl(array $viewConfig)
    {
    
        $templateString = <<<'EOD'
        {% verbatim %}{% extends 'base.html.twig' %}{% endverbatim %}
        {% for key, block in components %}
        {% verbatim %}{% block {% endverbatim %}{{ key }} {% verbatim %}%} {% endverbatim %}
        {% for component in block %}
        {% if component.type == 'vue' %}
        <{{ component.name }}{{component.compiledBinds|raw}}></{{ component.name }}>
        {% else %}
        {% verbatim %}{% include {% endverbatim %}'components/{{ component.name }}.twig' {{component.compiledBinds|raw}}{% verbatim %} %} {% endverbatim %}
        {% endif %}
        {% endfor %}
        {% verbatim %}{% endblock %}{% endverbatim %}

        {% endfor %}
        EOD;

        $template = $this->twig->createTemplate($templateString);
        return $template->render(array('components'=>$viewConfig['components']));

    }


    public function generateController(string $viewName) 
    {

        $viewConfig = $this->viewsConfig->getConfig($viewName);

        $nameSpaceStr = 'App\Controller\Views';
        $className = Str::asCamelCase($viewName) .'ViewController';        
        $fqcn = $nameSpaceStr.'\\'.$className;
        $exist = class_exists($fqcn);

        $namespace = new PhpNamespace($nameSpaceStr);
        // $namespace->addUse('Bar\AliasedClass');

        if ($exist) {
            // load class from file
            $class = ClassType::from($fqcn, withBodies: true);
            $namespace->add($class);
            echo "class exist, loading ${fqcn}\n";
        }
        else {
            $class = $namespace->addClass($className);
        }

        $str = 'any string';
        $num = 3;


        $method = $class->addMethod('index')
            ->addAttribute('Route', ['/{contenttype}', 'name' => 'app_coso_index', 'methods' => ['GET']])
            ->setReturnType('Response')
            ->setBody($this->getIndexContentTpl($viewConfig, $viewName));


        $method->addParameter('contenttype')
            ->setType('string'); 

        echo $namespace;
        echo $viewName;

        
    }


    private function getIndexContentTpl(array $viewConfig, string $viewName)
    {
    
        $templateString = <<<'EOD'
        return $this->render('views/{{viewName}}.twig', [
            'records' => [],
            'contenttype' => $contenttype,
            'url' => ''
        ]);
        EOD;

        $template = $this->twig->createTemplate($templateString);
        return $template->render(array('viewName'=>$viewName));

    }


}