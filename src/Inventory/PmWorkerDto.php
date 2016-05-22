<?php
/**
 * Created by PhpStorm.
 * User: ww
 * Date: 11.10.15
 * Time: 22:53
 */
namespace React\ProcessManager\Inventory;

use App\FWIndependent\React\PublisherPulsar\Inventory\PerformerSocketsParamsDto;

class PmWorkerDto
{
    /**
     * @var PerformerSocketsParamsDto
     */
    protected $performerSocketsParamsDto;

    /**
     * @return PerformerSocketsParamsDto
     */
    public function getPerformerSocketsParamsDto()
    {
        return $this->performerSocketsParamsDto;
    }

    /**
     * @param PerformerSocketsParamsDto $performerSocketsParamsDto
     */
    public function setPerformerSocketsParamsDto($performerSocketsParamsDto)
    {
        $this->performerSocketsParamsDto = $performerSocketsParamsDto;
    }


}