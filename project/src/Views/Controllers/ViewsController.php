<?php

namespace Bundle\Views\Controller;

use App\EntityMap;
use App\ContentConfig;
use App\Hydrator;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use League\Flysystem\FilesystemOperator;

class ViewsController extends AbstractController
{

    private FileSystemOperator $filesystem;
    private ContentConfig $contentTypesConfig;
    private Hydrator $hydrator;

    public function __construct(FileSystemOperator $defaultFilesystem, ContentConfig $contentConfig, Hydrator $hydrator)
    {
        $this->filesystem = $defaultFilesystem;
        $this->contentTypesConfig = $contentConfig;
        $this->hydrator = $hydrator;
    }

    public function view(Request $request, EntityManagerInterface $em, EntityMap $emap): Response
    {   
        $paramValue = $request->attributes->get('id');
        dump($paramValue);

        $entityClass = $emap->getEntity($contenttype);

        $repo = $em->getRepository($entityClass);

        $records = $repo->findAll();

        return $this->render('views/backoffice.list.html.twig', [
            'records' => $records,
            'fields' => $this->contentTypesConfig->getConfig($contenttype)['fields'],
            'contenttype' => $contenttype,
            'url' => ''
        ]);
    }

}
