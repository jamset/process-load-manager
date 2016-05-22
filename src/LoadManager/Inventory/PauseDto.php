<?php
/**
 * Created by PhpStorm.
 * User: ww
 * Date: 29.09.15
 * Time: 8:02
 */
namespace React\ProcessManager\LoadManager\Inventory;

use React\ProcessManager\LoadManager\Abstracts\BaseLmControlDto;

class PauseDto extends BaseLmControlDto
{
    /**
     * @var array
     */
    protected $pidsForPause = [];

    /**
     * @return array
     */
    public function getPidsForPause()
    {
        return $this->pidsForPause;
    }

    /**
     * @param array $pidsForPause
     */
    public function setPidsForPause($pidsForPause)
    {
        $this->pidsForPause = $pidsForPause;
    }

}