<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\RedirectResponse;
use App\Entity\Comment;
use App\Entity\Conference;
use App\Form\CommentType;
use App\Message\CommentMessage;
use App\Repository\CommentRepository;
use App\Repository\ConferenceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Notifier\Notification\Notification;
use Symfony\Component\Notifier\NotifierInterface;
use Symfony\Component\Routing\Attribute\Route;
use Twig\Environment;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class ConferenceController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly MessageBusInterface $bus,
        private readonly Environment $twig,
        private readonly FormFactoryInterface $formFactory,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    #[Route('/')]
    public function indexNoLocale(): Response
    {
        $homepageUrl = $this->urlGenerator->generate('homepage', ['_locale' => 'en']);
        return new RedirectResponse($homepageUrl, 302);
    }

    #[Route('/{_locale<%app.supported_locales%>}/', name: 'homepage')]
    public function index(ConferenceRepository $conferenceRepository): Response
    {
        $indexHtml = $this->twig->render('conference/index.html.twig', [
            'conferences' => $conferenceRepository->findAll(),
        ]);
        return new Response($indexHtml);
    }

    #[Route('/{_locale<%app.supported_locales%>}/conference_header', name: 'conference_header')]
    public function conferenceHeader(ConferenceRepository $conferenceRepository): Response
    {
        $headerHtml = $this->twig->render('conference/header.html.twig', [
            'conferences' => $conferenceRepository->findAll(),
        ]);

        return new Response($headerHtml);
    }

    #[Route('/{_locale<%app.supported_locales%>}/conference/{slug}', name: 'conference')]
    public function show(
        Request $request,
        Conference $conference,
        CommentRepository $commentRepository,
        NotifierInterface $notifier,
        #[Autowire('%photo_dir%')] string $photoDir,
    ): Response {

        $comment = new Comment();
        $form = $this->formFactory->create(CommentType::class, $comment);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $comment->setConference($conference);
            if ($photo = $form['photo']->getData()) {
                $filename = bin2hex(random_bytes(6)).'.'.$photo->guessExtension();
                $photo->move($photoDir, $filename);
                $comment->setPhotoFilename($filename);
            }

            $this->entityManager->persist($comment);
            $this->entityManager->flush();

            $context = [
                'user_ip' => $request->getClientIp(),
                'user_agent' => $request->headers->get('user-agent'),
                'referrer' => $request->headers->get('referer'),
                'permalink' => $request->getUri(),
            ];

            $this->bus->dispatch(new CommentMessage($comment->getId(), $context));

            $notifier->send(new Notification('Благодарим вас за отзыв; ваш комментарий будет опубликован после модерации.', ['browser']));

            $url = $this->urlGenerator->generate('conference', ['slug' => $conference->getSlug()]);
            return new RedirectResponse($url, 302);
        }

        if ($form->isSubmitted()) {
            $notifier->send(new Notification('Можете ли вы проверить свою заявку? С ней возникли некоторые проблемы.', ['browser']));
        }


        $offset = max(0, $request->query->getInt('offset', 0));
        $paginator = $commentRepository->getCommentPaginator($conference, $offset);

        $showHtml = $this->twig->render('conference/show.html.twig', [
            'conference' => $conference,
            'comments' => $paginator,
            'previous' => $offset - CommentRepository::COMMENTS_PER_PAGE,
            'next' => min(count($paginator), $offset + CommentRepository::COMMENTS_PER_PAGE),
            'comment_form' => $form->createView(),
        ]);
        return new Response($showHtml);
    }
}
