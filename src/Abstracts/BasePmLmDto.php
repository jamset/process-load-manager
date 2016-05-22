<?php
/**
 * Created by PhpStorm.
 * User: ww
 * Date: 20.10.15
 * Time: 7:44
 */
namespace React\ProcessManager\Abstracts;

use React\FractalBasic\Abstracts\Inventory\BaseReactControlDto;
use React\ProcessManager\Inventory\PmLmSocketsParamsDto;

class BasePmLmDto extends BaseReactControlDto
{

    /**
     * @var PmLmSocketsParamsDto
     */
    protected $pmLmSocketsParams;

    /**
     * @return PmLmSocketsParamsDto
     */
    public function getPmLmSocketsParams()
    {
        return $this->pmLmSocketsParams;
    }

    /**
     * @param PmLmSocketsParamsDto $pmLmSocketsParams
     */
    public function setPmLmSocketsParams($pmLmSocketsParams)
    {
        $this->pmLmSocketsParams = $pmLmSocketsParams;
    }

}