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

namespace Comment\Tests\Unit\Api;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use Comment\Api\Resource\Comment as CommentResource;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * What the front API of the module promises, read off the resource itself.
 *
 * Two of these are security rules rather than shape: the API refuses nothing by default, so
 * an operation without an explicit `security` is open by accident rather than on purpose,
 * and an author's e-mail address must never be in a read group — the list is public.
 */
final class CommentResourceContractTest extends TestCase
{
    /**
     * @return list<\ApiPlatform\Metadata\Operation>
     */
    private function operations(): array
    {
        $attributes = (new \ReflectionClass(CommentResource::class))->getAttributes(ApiResource::class);

        self::assertCount(1, $attributes, 'The resource carries no #[ApiResource]');

        $operations = $attributes[0]->newInstance()->getOperations();

        self::assertNotNull($operations);

        return array_values(iterator_to_array($operations));
    }

    public function testEveryOperationIsUnderTheFrontPrefixAndCarriesItsOwnSecurity(): void
    {
        $operations = $this->operations();

        self::assertNotEmpty($operations);

        foreach ($operations as $operation) {
            $uriTemplate = (string) $operation->getUriTemplate();

            self::assertStringStartsWith('/front/comments', $uriTemplate);
            self::assertNotNull(
                $operation->getSecurity(),
                \sprintf('%s carries no security expression, and the API denies nothing by default', $uriTemplate)
            );
        }
    }

    public function testTheListAndThePostAreBothDeclared(): void
    {
        $classes = array_map(static fn (object $operation): string => $operation::class, $this->operations());

        self::assertContains(GetCollection::class, $classes);
        self::assertContains(Post::class, $classes);
    }

    public function testTheListNeverCarriesTheAuthorsEmail(): void
    {
        $readable = $this->propertiesInGroup(CommentResource::GROUP_FRONT_READ);

        self::assertNotContains('email', $readable, 'The e-mail address of an author is readable on a public list');
        self::assertNotContains('status', $readable, 'The moderation status is not the visitor\'s business');
    }

    public function testAVisitorReadsTheVerifiedPurchaseMention(): void
    {
        self::assertContains('verified', $this->propertiesInGroup(CommentResource::GROUP_FRONT_READ));
    }

    public function testAnAnonymousVisitorCanStillSignTheirComment(): void
    {
        $writable = $this->propertiesInGroup(CommentResource::GROUP_FRONT_WRITE);

        self::assertContains('username', $writable);
        self::assertContains('email', $writable);
        self::assertContains('content', $writable);
        self::assertContains('rating', $writable);
        self::assertContains('ref', $writable);
        self::assertContains('refId', $writable);
    }

    /**
     * @return list<string>
     */
    private function propertiesInGroup(string $group): array
    {
        $properties = [];

        foreach ((new \ReflectionClass(CommentResource::class))->getProperties() as $property) {
            foreach ($property->getAttributes(Groups::class) as $attribute) {
                if (\in_array($group, (array) $attribute->getArguments()[0], true)) {
                    $properties[] = $property->getName();
                }
            }
        }

        return $properties;
    }
}
