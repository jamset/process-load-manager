<?php
/**
 * Created by PhpStorm.
 * User: ww
 * Date: 29.09.15
 * Time: 8:02
 */
namespace React\ProcessManager\LoadManager\Inventory;

use React\ProcessManager\LoadManager\Abstracts\BaseLmControlDto;

class ContinueDto extends BaseLmControlDto
{

    /**
     * @var array
     */
    protected $pidsForContinue = [];

    /**
     * @return array
     */
    public function getPidsForContinue()
    {
        return $this->pidsForContinue;
    }

    /**
     * @param array $pidsForContinue
     */
    public function setPidsForContinue($pidsForContinue)
    {
        $this->pidsForContinue = $pidsForContinue;
    }
}