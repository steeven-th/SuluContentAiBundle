<?php

declare(strict_types=1);

namespace ItechWorld\SuluContentAiBundle\Controller\Admin;

use Doctrine\ORM\EntityManagerInterface;
use FOS\RestBundle\View\ViewHandlerInterface;
use Sulu\Component\Rest\AbstractRestController;
use Sulu\Component\Rest\ListBuilder\Doctrine\DoctrineListBuilderFactoryInterface;
use Sulu\Component\Rest\ListBuilder\Metadata\FieldDescriptorFactoryInterface;
use Sulu\Component\Rest\ListBuilder\PaginatedRepresentation;
use Sulu\Component\Rest\RestHelperInterface;
use Sulu\Component\Security\SecuredControllerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Generic REST CRUD controller for the bundle's simple, non-localized admin
 * entities (AI experts, prompts). Concrete controllers only declare the entity
 * specifics; listing/paging is delegated to Sulu's Doctrine list builder.
 */
abstract class AbstractCrudController extends AbstractRestController implements SecuredControllerInterface
{
    public function __construct(
        #[Autowire(service: 'fos_rest.view_handler')]
        ViewHandlerInterface $viewHandler,
        #[Autowire(service: 'sulu_core.list_builder.field_descriptor_factory')]
        private readonly FieldDescriptorFactoryInterface $fieldDescriptorFactory,
        #[Autowire(service: 'sulu_core.doctrine_list_builder_factory')]
        private readonly DoctrineListBuilderFactoryInterface $listBuilderFactory,
        #[Autowire(service: 'sulu_core.doctrine_rest_helper')]
        private readonly RestHelperInterface $restHelper,
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct($viewHandler);
    }

    /**
     * @return class-string
     */
    abstract protected function getEntityClass(): string;

    abstract protected function getResourceKey(): string;

    abstract protected function getListKey(): string;

    abstract protected function createEntity(): object;

    /**
     * @param array<string, mixed> $data
     */
    abstract protected function applyData(object $entity, array $data): void;

    /**
     * @return array<string, mixed>
     */
    abstract protected function entityToArray(object $entity): array;

    /**
     * Paginated list (the admin always requests ?flat=true).
     */
    public function cgetAction(Request $request): Response
    {
        $fieldDescriptors = $this->fieldDescriptorFactory->getFieldDescriptors($this->getListKey());
        $listBuilder = $this->listBuilderFactory->create($this->getEntityClass());
        $this->restHelper->initializeListBuilder($listBuilder, $fieldDescriptors);

        $list = new PaginatedRepresentation(
            $listBuilder->execute(),
            $this->getResourceKey(),
            (int) $listBuilder->getCurrentPage(),
            (int) $listBuilder->getLimit(),
            (int) $listBuilder->count(),
        );

        return $this->handleView($this->view($list, 200));
    }

    public function getAction(int $id): Response
    {
        $entity = $this->find($id);
        if (null === $entity) {
            return $this->handleView($this->view(null, 404));
        }

        return $this->handleView($this->view($this->entityToArray($entity), 200));
    }

    public function postAction(Request $request): Response
    {
        $entity = $this->createEntity();
        $this->applyData($entity, $request->request->all());
        $this->entityManager->persist($entity);
        $this->entityManager->flush();

        return $this->handleView($this->view($this->entityToArray($entity), 201));
    }

    public function putAction(Request $request, int $id): Response
    {
        $entity = $this->find($id);
        if (null === $entity) {
            return $this->handleView($this->view(null, 404));
        }

        $this->applyData($entity, $request->request->all());
        $this->entityManager->flush();

        return $this->handleView($this->view($this->entityToArray($entity), 200));
    }

    public function deleteAction(int $id): Response
    {
        $entity = $this->find($id);
        if (null !== $entity) {
            $this->entityManager->remove($entity);
            $this->entityManager->flush();
        }

        return $this->handleView($this->view(null, 204));
    }

    /**
     * Non-localized resources.
     */
    public function getLocale(Request $request): ?string
    {
        return null;
    }

    private function find(int $id): ?object
    {
        return $this->entityManager->getRepository($this->getEntityClass())->find($id);
    }
}
