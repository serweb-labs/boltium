<?php   
namespace Bundle\Views\Config;

use Bolt\Filesystem\Handler\DirectoryInterface;
use Bolt\Filesystem\Handler\ParsableInterface;
use Bolt\Filesystem\Exception\FileNotFoundException;

class ViewsConfig 
{
    private array $map = [];

    /** @var Silex\Application */
    protected $app;

    public function __construct($app)
    {
        $this->app = $app;
        $this->map = $this->parseConfigYaml('views.yml');
        foreach ($this->map as $viewName => $view) {
            foreach ($this->map[$viewName]['components'] as $blockName => &$components) {
                foreach ($components as &$component) {
                    $component['compiledBinds'] = $this->compileBinds($component);
                }
            }
        }
    }

    public function getConfig(string $viewName)
    {
        return $this->map[$viewName];
    }

    public function getConfigAll()
    {
        return $this->map;
    }

    protected function parseConfigYaml($filename, DirectoryInterface $directory = null)
    {
        $directory = $directory ?: $this->app['filesystem']->getDir('config://');

        try {
            $file = $directory->get($filename);
        } catch (FileNotFoundException $e) {
            // Copy in dist files if applicable
            $distFiles = ['config.yml', 'contenttypes.yml', 'menu.yml', 'permissions.yml', 'routing.yml', 'taxonomy.yml'];
            if ($directory->getMountPoint() !== 'config' || !in_array($filename, $distFiles)) {
                return [];
            }

            $this->app['filesystem']->copy("bolt://app/config/$filename.dist", "config://$filename");
            $file = $directory->get($filename);
        }

        if (!$file instanceof ParsableInterface) {
            throw new \LogicException('File is not parsable.');
        }

        $yml = $file->parse() ?: [];

        // Unset the repeated nodes key after parse
        unset($yml['__nodes']);

        return $yml;
    }

    private function compileBinds(array $component)
    {
        $name = $component['name'];
        $tpl = '';
        if ($component['type'] == 'vue') {
            $attrs = isset($component['binds']) ? $component['binds'] : [];
            foreach ($attrs as $attr) {
                $key = $attr['key'];
                $value = $attr['value'];
                $tpl .= " {$key}=\"{$value}\"";
            }

            return $tpl;
        }
        else if ($component['type'] == 'twig') {
            $with = isset($component['binds']) ? $component['binds'] : [];
            $tpl .= "with {";
            $first = true;
            foreach ($with as $w) {
                $key = $w['key'];
                $value = $w['value'];
                if (!$first) {
                    $tpl .= ",";
                }
                $tpl .= " {$key}: {$value}";
                $first = false;
            }
            $tpl .= " } only";
        }
        return $tpl;
    }

}