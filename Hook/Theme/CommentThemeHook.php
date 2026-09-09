<?php

declare(strict_types=1);

/*************************************************************************************/
/*      This file is part of the Thelia package.                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : dev@thelia.net                                                       */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      For the full copyright and license information, please view the LICENSE.txt  */
/*************************************************************************************/

namespace Comment\Hook\Theme;

use Comment\Service\Front\CommentDefinitionResolver;
use Thelia\Core\Hook\Theme\ThemeHookInterface;
use Twig\Environment;

final readonly class CommentThemeHook implements ThemeHookInterface
{
    private const HOOK_NAME = 'product.bottom';
    private const REF = 'product';

    public function __construct(
        private Environment $twig,
        private CommentDefinitionResolver $definitionResolver,
    ) {
    }

    public function supports(string $hookName): bool
    {
        return self::HOOK_NAME === $hookName;
    }

    /**
     * @param array<string, mixed> $parameters
     */
    public function render(string $hookName, array $parameters): string
    {
        $productId = $this->productId($parameters);

        if (0 === $productId) {
            return '';
        }

        // Comments turned off for the shop or for this product: no block at all, not an
        // empty one.
        if ($this->definitionResolver->resolve(self::REF, $productId)->hidden) {
            return '';
        }

        return $this->twig->render('@CommentModule/theme_hook/comment.html.twig', [
            'ref' => self::REF,
            'refId' => $productId,
        ]);
    }

    /**
     * @param array<string, mixed> $parameters
     */
    private function productId(array $parameters): int
    {
        $product = $parameters['product'] ?? null;

        if (\is_array($product)) {
            return (int) ($product['id'] ?? 0);
        }

        if (\is_object($product) && method_exists($product, 'getId')) {
            return (int) $product->getId();
        }

        return (int) ($parameters['product_id'] ?? 0);
    }
}
