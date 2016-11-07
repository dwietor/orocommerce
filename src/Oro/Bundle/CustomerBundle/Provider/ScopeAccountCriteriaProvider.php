<?php

namespace Oro\Bundle\CustomerBundle\Provider;

use Oro\Bundle\CustomerBundle\Entity\Account;
use Oro\Bundle\CustomerBundle\Entity\AccountUser;
use Oro\Bundle\ScopeBundle\Manager\AbstractScopeCriteriaProvider;
use Oro\Bundle\SecurityBundle\SecurityFacade;

class ScopeAccountCriteriaProvider extends AbstractScopeCriteriaProvider
{
    const ACCOUNT = 'account';

    /**
     * @var SecurityFacade
     */
    protected $securityFacade;

    /**
     * @param SecurityFacade $securityFacade
     */
    public function __construct(SecurityFacade $securityFacade)
    {
        $this->securityFacade = $securityFacade;
    }

    /**
     * @return array
     */
    public function getCriteriaForCurrentScope()
    {
        $loggedUser = $this->securityFacade->getLoggedUser();
        if (null !== $loggedUser && $loggedUser instanceof AccountUser) {
            return [self::ACCOUNT => $loggedUser->getAccount()];
        }

        return [];
    }

    /**
     * @return string
     */
    public function getCriteriaField()
    {
        return static::ACCOUNT;
    }

    /**
     * @return string
     */
    protected function getCriteriaValueType()
    {
        return Account::class;
    }
}
