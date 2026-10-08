<?php
namespace SuperSoft\Component\Newsletterbridge\Site\View\Newsletters;
defined('_JEXEC') or die;
use SuperSoft\Component\Newsletterbridge\Site\View\BridgeView;
class HtmlView extends BridgeView
{
    public $items;
    public $pagination;
    public $state;
    public function display($tpl = null)
    {
        $this->initialise();
        $model = $this->getModel();
        $this->items = $model->getItems();
        $this->pagination = $model->getPagination();
        $this->state = $model->getState();
        if ($model->getErrors()) { throw new \RuntimeException('COM_NEWSLETTERBRIDGE_LOAD_FAILED', 500); }
        return parent::display($tpl);
    }
}
