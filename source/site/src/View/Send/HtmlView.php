<?php
namespace SuperSoft\Component\Newsletterbridge\Site\View\Send;
defined('_JEXEC') or die;
use Joomla\CMS\Factory;
use Joomla\CMS\Pagination\Pagination;
use SuperSoft\Component\Newsletterbridge\Site\View\BridgeView;
use SuperSoft\Component\Newsletterbridge\Site\Service\InputGuard;
class HtmlView extends BridgeView
{
    public $item;
    public $jobs;
    public $pagination;
    public function display($tpl = null)
    {
        $this->initialise();
        $input = Factory::getApplication()->getInput();
        $id = InputGuard::id($input->get('id', '', 'raw'));
        $this->item = $this->bridge->item($id);
        $this->policy->require($this->policy->send($this->item));
        $start = max(0, $input->getInt('limitstart'));
        $result = $this->bridge->jobs($id, $start);
        $this->jobs = $result['items'];
        $this->pagination = new Pagination($result['total'], $start, 20);
        return parent::display($tpl);
    }
}
