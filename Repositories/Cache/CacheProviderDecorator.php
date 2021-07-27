<?php

namespace Modules\Iappointment\Repositories\Cache;

use Modules\Iappointment\Repositories\ProviderRepository;
use Modules\Core\Repositories\Cache\BaseCacheDecorator;

class CacheProviderDecorator extends BaseCacheDecorator implements ProviderRepository
{
    public function __construct(ProviderRepository $provider)
    {
        parent::__construct();
        $this->entityName = 'iappointment.providers';
        $this->repository = $provider;
    }
}
