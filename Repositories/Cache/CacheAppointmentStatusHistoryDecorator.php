<?php

namespace Modules\Iappointment\Repositories\Cache;

use Modules\Iappointment\Repositories\AppointmentStatusHistoryRepository;
use Modules\Core\Repositories\Cache\BaseCacheDecorator;

class CacheAppointmentStatusHistoryDecorator extends BaseCacheDecorator implements AppointmentStatusHistoryRepository
{
    public function __construct(AppointmentStatusHistoryRepository $appointmentstatushistory)
    {
        parent::__construct();
        $this->entityName = 'iappointment.appointmentstatushistories';
        $this->repository = $appointmentstatushistory;
    }
}
