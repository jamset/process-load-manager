<?php
/**
 * Created by PhpStorm.
 * User: ww
 * Date: 11.10.15
 * Time: 22:39
 */
namespace React\ProcessManager\Inventory;

class PmLmSocketsParamsDto
{

    /**Allow to communicate PM/LM by REQ/REP connection
     * @var string
     */
    protected $pmLmRequestAddress;

    /**
     * @return string
     */
    public function getPmLmRequestAddress()
    {
        return $this->pmLmRequestAddress;
    }

    /**
     * @param string $pmLmRequestAddress
     */
    public function setPmLmRequestAddress($pmLmRequestAddress)
    {
        $this->pmLmRequestAddress = $pmLmRequestAddress;
    }


}