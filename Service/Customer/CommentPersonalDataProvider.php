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

namespace Comment\Service\Customer;

use Thelia\Domain\Customer\Service\CustomerPersonalDataProviderInterface;
use Thelia\Model\Customer;

/**
 * Puts the module's comments in the core's personal data export and anonymization.
 *
 * Implementing the interface is enough to be called: the kernel tags every implementation, and
 * both CustomerPersonalDataExporter and CustomerAnonymizer iterate over that tag.
 *
 * Anonymization runs inside the core's transaction, so anything thrown here rolls the whole
 * operation back.
 */
final readonly class CommentPersonalDataProvider implements CustomerPersonalDataProviderInterface
{
    public function __construct(
        private CommentPersonalData $personalData,
    ) {
    }

    public function getPersonalDataSectionName(): string
    {
        return CommentPersonalData::SECTION;
    }

    /**
     * @return array<int|string, mixed>
     */
    public function exportPersonalData(Customer $customer): array
    {
        return $this->personalData->export((int) $customer->getId());
    }

    public function anonymizePersonalData(Customer $customer): void
    {
        $this->personalData->anonymize((int) $customer->getId());
    }
}
