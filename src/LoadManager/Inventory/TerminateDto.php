<?php
/**
 * Created by PhpStorm.
 * User: ww
 * Date: 29.09.15
 * Time: 8:00
 */
namespace React\ProcessManager\LoadManager\Inventory;

use React\ProcessManager\LoadManager\Abstracts\BaseLmControlDto;

class TerminateDto extends BaseLmControlDto
{

    /**
     * @var array
     */
    protected $pidsForTerminate = [];

    /**
     * @return array
     */
    public function getPidsForTerminate()
    {
        return $this->pidsForTerminate;
    }

    /**
     * @param array $pidsForTerminate
     */
    public function setPidsForTerminate($pidsForTerminate)
    {
        $this->pidsForTerminate = $pidsForTerminate;
    }


}