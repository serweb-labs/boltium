<?php

namespace Bolt\Nut;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Bundle\Views\Config\ViewsConfig;

/**
 * Database pre-fill command.
 *
 * @author Gawain Lynch <gawain.lynch@gmail.com>
 */
class GenerateViews extends BaseCommand
{
    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('generate:views')
            ->setDescription('Generate templates for views')
            ->addArgument('view', InputArgument::IS_ARRAY | InputArgument::OPTIONAL, 'A list of views to generate. If this argument is empty, all ContentTypes are used.')
        ;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $views = new ViewsConfig($this->app);
        $viewArg = $input->getArgument('view');
    
        $this->io->title($viewArg[0]);

        if ($viewArg) {
            $viewConfig = [$viewArg[0] => $views->getConfig($viewArg[0])];
        }
        else {
            $viewConfig = $views->getConfigAll();
        }

        $this->io->title('Creating ' . count($viewConfig) . ' twig templates.');
        
        foreach ($viewConfig as $key => $value) {
            $body = $this->getContentTpl($value);
            // $environment = $this->app['config.environment'];
            // $filename =  $environment->boltPath . "/templates" . "/" . "$key.twig";
            $filename = "$key.twig";
            $this->writeTemplate($filename, $body);
        }

    }

    private function writeTemplate(string $filename, string $body) {
        $filesystem = $this->app['filesystem']->getFile('root://templates/'.$filename);
        $filesystem->put($body);
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

        $template = $this->app['twig']->createTemplate($templateString);
        return $template->render(array('components'=>$viewConfig['components']));

    }

}
