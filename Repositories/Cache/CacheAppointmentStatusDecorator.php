<?php

namespace Modules\Iappointment\Repositories\Cache;

use Modules\Iappointment\Repositories\AppointmentStatusRepository;
use Modules\Core\Repositories\Cache\BaseCacheDecorator;

class CacheAppointmentStatusDecorator extends BaseCacheDecorator implements AppointmentStatusRepository
{
    public function __construct(AppointmentStatusRepository $appointmentstatus)
    {
        parent::__construct();
        $this->entityName = 'iappointment.appointmentstatuses';
        $this->repository = $appointmentstatus;
    }
}
