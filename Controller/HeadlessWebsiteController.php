<?php

declare(strict_types=1);

/*
 * This file is part of Sulu.
 *
 * (c) Sulu GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Sulu\Bundle\HeadlessBundle\Controller;

use JMS\Serializer\SerializationContext;
use JMS\Serializer\SerializerInterface;
use Sulu\Bundle\HeadlessBundle\Content\StructureResolverInterface;
use Sulu\Bundle\PreviewBundle\Preview\Preview;
use Sulu\Component\Content\Compat\PageInterface;
use Sulu\Component\Content\Compat\StructureInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

class HeadlessWebsiteController extends AbstractController
{
    /**
     * We cannot set the typehint of the $structure parameter to PageInterface because the ArticleBundle does not
     * implement that interface. Therefore we need to define the type via phpdoc to satisfy phpstan.
     *
     * @param PageInterface $structure
     */
    public function indexAction(
        Request $request,
        StructureInterface $structure,
        bool $preview = false,
        bool $partial = false
    ): Response {
        /** @var string $requestFormat */
        $requestFormat = $request->getRequestFormat();
        $data = $this->resolveStructure($structure);
        $json = $this->serializeData($data);

        if ('json' !== $request->getRequestFormat()) {
            return $this->renderTemplateResponse($structure, $requestFormat, $preview, [
                'jsonData' => $json,
                'data' => $data,
            ]);
        }

        return new Response(
            $json,
            200,
            [
                'Content-Type' => 'application/json',
            ]
        );
    }

    /**
     * @param PageInterface $structure
     * @param mixed[] $parameters
     */
    private function renderTemplateResponse(
        StructureInterface $structure,
        string $requestFormat,
        bool $preview,
        array $parameters
    ): Response {
        $viewTemplate = $structure->getView() . '.' . $requestFormat . '.twig';

        if (!$this->get('twig')->getLoader()->exists($viewTemplate)) {
            throw new HttpException(406, sprintf('Page does not exist in "%s" format.', $requestFormat));
        }

        if ($preview) {
            $parameters['previewParentTemplate'] = $viewTemplate;
            $parameters['previewContentReplacer'] = Preview::CONTENT_REPLACER;

            return $this->render('@SuluWebsite/Preview/preview.html.twig', $parameters);
        }

        return $this->render($viewTemplate, $parameters);
    }

    /**
     * @return mixed[]
     */
    protected function resolveStructure(StructureInterface $structure): array
    {
        /** @var StructureResolverInterface $structureResolver */
        $structureResolver = $this->get('sulu_headless.structure_resolver');

        return $structureResolver->resolve($structure, $structure->getLanguageCode());
    }

    /**
     * @param mixed[] $data
     */
    protected function serializeData(array $data): string
    {
        return $this->get('jms_serializer')->serialize(
            $data,
            'json',
            (new SerializationContext())->setSerializeNull(true)
        );
    }

    /**
     * @return mixed[]
     */
    public static function getSubscribedServices(): array
    {
        $subscribedServices = parent::getSubscribedServices();
        $subscribedServices['sulu_headless.structure_resolver'] = StructureResolverInterface::class;
        $subscribedServices['jms_serializer'] = SerializerInterface::class;

        return $subscribedServices;
    }
}
