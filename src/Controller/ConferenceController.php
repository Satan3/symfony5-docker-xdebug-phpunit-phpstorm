<?php

namespace App\Controller;

use App\Repository\CommentRepository;
use App\Repository\ConferenceRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ConferenceController extends AbstractController {
    private $conferenceRepository;
    private $commentRepository;

    public function __construct(ConferenceRepository $conferenceRepository, CommentRepository $commentRepository) {
        $this->conferenceRepository = $conferenceRepository;
        $this->commentRepository = $commentRepository;
    }

    /**
     * @Route("/", name="homepage")
     * @param ConferenceRepository $conferenceRepository
     * @return Response
     */
    public function index(ConferenceRepository $conferenceRepository): Response {
        return $this->render('conference/index.html.twig', [
            'conferences' => $conferenceRepository->findAll(),
        ]);
    }

    /**
     * @Route("/conference/{id}", name="conference")
     * @param Request $request
     * @param int $id
     * @return Response
     */
    public function show(Request $request, int $id): Response {
        if (!$conference = $this->conferenceRepository->find($id)) {
            throw $this->createNotFoundException();
        }

        $offset = max(0, $request->query->getInt('offset'));
        $paginator = $this->commentRepository->getCommentPaginator($conference, $offset);

        return $this->render('conference/show.html.twig', [
            'conference' => $conference,
            'comments' => $paginator,
            'previous' => $offset - CommentRepository::PAGINATION_PER_PAGE,
            'next' => min(count($paginator), $offset + CommentRepository::PAGINATION_PER_PAGE),
        ]);
    }
}
