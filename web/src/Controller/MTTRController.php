<?php

namespace App\Controller;

use App\Entity\BugMTTR;
use App\Repository\BugMTTRRepository;
use App\Service\MTTRService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/mttr')]
class MTTRController extends AbstractController
{
    public function __construct(
        private readonly MTTRService $mttrService,
        private readonly BugMTTRRepository $mttrRepository,
        private readonly string $jiraHost,
    ) {
    }

    #[Route('/', name: 'app_mttr_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $page = max(1, $request->query->getInt('page', 1));
        $limit = $request->query->getInt('limit', 10);
        
        $stats = $this->mttrRepository->getAverageMTTR();
        $bugs = $this->mttrRepository->getPaginatedBugs($page, $limit);
        $total = $this->mttrRepository->countBugs();
        
        return $this->render('mttr/index.html.twig', [
            'stats' => $stats,
            'bugs' => $bugs,
            'page' => $page,
            'limit' => $limit,
            'totalPages' => ceil($total / $limit),
            'jira_host' => $this->jiraHost,
        ]);
    }

    #[Route('/sync', name: 'app_mttr_sync', methods: ['GET', 'POST'])]
    public function sync(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            try {
                $startDate = new \DateTime($request->request->get('start_date', '-3 months'));
                $endDate = new \DateTime($request->request->get('end_date', 'now'));
                
                // Si on a déjà des bugs synchronisés, on reprend depuis le dernier
                $lastBug = $this->mttrRepository->getLastSyncedBug();
                $lastBugKey = $lastBug ? $lastBug->getBugKey() : null;
                
                $bugs = $this->mttrService->getBugMTTRStats($startDate, $endDate, $lastBugKey);
                
                foreach ($bugs as $bugData) {
                    // Vérifier si le bug existe déjà
                    $bug = $this->mttrRepository->findOneBy(['bugKey' => $bugData['key']]) ?? new BugMTTR();
                    
                    $bug->setBugKey($bugData['key'])
                        ->setSummary($bugData['summary'])
                        ->setCreatedAt(new \DateTimeImmutable($bugData['created']))
                        ->setCurrentStatus($bugData['currentStatus'])
                        ->setSyncedAt(new \DateTimeImmutable());

                    if (isset($bugData['mttrStats'])) {
                        if ($bugData['mttrStats']['createdToTermine']) {
                            $bug->setTermineAt(new \DateTimeImmutable('@' . ($bug->getCreatedAt()->getTimestamp() + $bugData['mttrStats']['createdToTermine'])));
                        }
                        if ($bugData['mttrStats']['aFaireToTermine']) {
                            $bug->setAFaireAt(new \DateTimeImmutable('@' . ($bug->getTermineAt()->getTimestamp() - $bugData['mttrStats']['aFaireToTermine'])));
                        }
                        if ($bugData['mttrStats']['aFaireToDevsTermines']) {
                            $bug->setDevsTerminesAt(new \DateTimeImmutable('@' . ($bug->getAFaireAt()->getTimestamp() + $bugData['mttrStats']['aFaireToDevsTermines'])));
                        }
                    }

                    $this->mttrRepository->save($bug, true);
                }

                $this->addFlash('success', sprintf('%d bugs synchronized successfully.', count($bugs)));
            } catch (\Exception $e) {
                $this->addFlash('error', 'Error synchronizing MTTR statistics: ' . $e->getMessage());
            }

            return $this->redirectToRoute('app_mttr_index');
        }

        return $this->render('mttr/sync.html.twig');
    }
} 