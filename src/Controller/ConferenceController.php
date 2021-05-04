<?php

namespace App\Controller;

use App\Entity\Comment;
use App\Form\CommentFormType;
use App\Repository\CommentRepository;
use App\Repository\ConferenceRepository;
use App\SpamChecker;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ConferenceController extends AbstractController
{
    private $conferenceRepository;
    private $commentRepository;
    private $entityManager;

    public function __construct(
        ConferenceRepository $conferenceRepository,
        CommentRepository $commentRepository,
        EntityManagerInterface $entityManager
    ) {
        $this->conferenceRepository = $conferenceRepository;
        $this->commentRepository = $commentRepository;
        $this->entityManager = $entityManager;
    }

    /**
     * @Route("/", name="homepage")
     * @param ConferenceRepository $conferenceRepository
     * @return Response
     */
    public function index(ConferenceRepository $conferenceRepository): Response
    {
        return $this->render(
            'conference/index.html.twig',
            [
                'conferences' => $conferenceRepository->findAll(),
            ]
        );
    }

    /**
     * @Route("/conference/{slug}", name="conference")
     * @param Request $request
     * @param string $slug
     * @param string $photoDir
     * @param SpamChecker $spamChecker
     * @return Response
     * @throws Exception
     */
    public function show(
        Request $request,
        string $slug,
        string $photoDir,
        SpamChecker $spamChecker
    ): Response {
        if (!$conference = $this->conferenceRepository->findOneBy(['slug' => $slug])) {
            throw $this->createNotFoundException();
        }

        $comment = new Comment();
        $form = $this->createForm(CommentFormType::class, $comment);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $comment->setConference($conference);
            if ($photo = $form['photo']->getData()) {
                $filename = bin2hex(random_bytes(6) . '.' . $photo->getExtension());
                try {
                    $photo->move($photoDir, $filename);
                } catch (FileException $e) {
                }
                $comment->setPhotoFileName($filename);
            }
            $this->entityManager->persist($comment);

            $context = [
                'user_ip' => $request->getClientIp(),
                'user_agent' => $request->headers->get('user_agent'),
                'referrer' => $request->headers->get('referrer'),
                'permalink' => $request->getUri(),
            ];
            if (2 === $spamChecker->getSpamScore($comment, $context)) {
                throw new \RuntimeException('Blatant spam, go away!');
            }
            $this->entityManager->flush();

            return $this->redirectToRoute('conference', ['slug' => $conference->getSlug()]);
        }

        $offset = max(0, $request->query->getInt('offset'));
        $paginator = $this->commentRepository->getCommentPaginator($conference, $offset);

        return $this->render(
            'conference/show.html.twig',
            [
                'conference' => $conference,
                'comments' => $paginator,
                'previous' => $offset - CommentRepository::PAGINATION_PER_PAGE,
                'next' => min(count($paginator), $offset + CommentRepository::PAGINATION_PER_PAGE),
                'comment_form' => $form->createView(),
            ]
        );
    }
}
