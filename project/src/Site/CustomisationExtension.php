<?php

namespace Bundle\Site;

use Bolt\Extension\SimpleExtension;
use Silex\Application;
use Silex\ControllerCollection;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Bundle\Views\Config\ViewsConfig;

/**
 * Site bundle extension loader.
 *
 * This is the base bundle you can use to further customise Bolt for your
 * specific site.
 *
 * It is perfectly safe to remove this bundle, just remember to remove the
 * entry from your .bolt.yml or .bolt.php file.
 *
 * For more information on building bundles see https://docs.bolt.cm/extensions
 */
class CustomisationExtension extends SimpleExtension
{

     /**
     * {@inheritdoc}
     */
    protected function registerFrontendRoutes(ControllerCollection $collection)
    {

        // All requests to /koala
        $collection->match('/app/{view}', [$this, 'callbackKoalaCatching']);
    }

    /**
     * {@inheritdoc}
     */
    protected function registerTwigPaths()
    {
        return [];
        
        // return [
        //     'templates/views'   => ['namespace' => 'Views'],
        // ];
    }

    /**
     * @param Application $app
     * @param Request     $request
     *
     * @return Response
     */
    public function callbackKoalaCatching(Application $app, Request $request)
    {
        $viewsConfig = new ViewsConfig($app);

        //return new Response('Drop bear sighted!' . json_encode($viewsConfig->getConfigAll()), Response::HTTP_OK);


        $records = $app['query']->getContent('showcase');

        return $this->renderTemplate('views/backoffice.list.html.twig',  [
            'records' => $records,
            'fields' => [],
            'contenttype' => 'showcase',
            'url' => ''
            
        ]);
    }

    /**
     * Render and return the Twig file
     *
     * @return string
     */
    public function kangarooFunction()
    {
        return $this->renderTemplate('@Views/skippy.twig');
    }
}
