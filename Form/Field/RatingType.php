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

namespace Comment\Form\Field;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * A rating on a scale of 1 to `max`, posted as one radio per step and rendered as a row of stars
 * by the `rating_widget` block of the theme's form theme.
 *
 * Sits on TextType so the submitted value stays the plain string the comment table stores: the
 * back-office form and the legacy templates, which lay this field out by hand, are untouched.
 */
final class RatingType extends AbstractType
{
    public const DEFAULT_MAX = 5;

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefault('max', self::DEFAULT_MAX);
        $resolver->setAllowedTypes('max', 'int');
        $resolver->setAllowedValues('max', static fn (int $max): bool => $max >= 1);
    }

    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        $view->vars['max'] = $options['max'];
    }

    public function getParent(): string
    {
        return TextType::class;
    }

    public function getBlockPrefix(): string
    {
        return 'rating';
    }
}
