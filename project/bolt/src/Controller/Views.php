<?php

namespace Bolt\Controller;
  

use Bolt\Response\TemplateResponse;
use Symfony\Component\HttpFoundation\Request;


/**
 * Standard Frontend actions.
 *
 * This file acts as a grouping for the default front-end controllers.
 *
 * For overriding the default behavior here, please reference
 * https://docs.bolt.cm/templating/templates-routes#routing or the routing.yml
 * file in your configuration.
 */
class Views extends ConfigurableBase
{
    protected function getConfigurationRoutes()
    {
        return $this->app['config']->get('routing', []);
    }

    /**
     * @param Request $request
     *
     * @return TemplateResponse
     */
    public function view(Request $request)
    {
        $query = $this->app['query'];
        $records = $query->getContent('showcase');
        return $this->render('views/backoffice.list.html.twig', [
            'records' => $records,
            'fields' => [],
            'contenttype' => 'showcase',
            'url' => ''
        ], []);
    }

}
