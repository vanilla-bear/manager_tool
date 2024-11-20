<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\RouterInterface;

class HomeController extends AbstractController {

  #[Route('/', name: 'app_home')]
  public function index(RouterInterface $router): Response {
    return $this->render('home/index.html.twig', [
      'controller_name' => 'HomeController',
    ]);
  }

}
