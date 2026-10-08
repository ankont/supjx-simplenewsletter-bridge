<?php
namespace SuperSoft\Component\Newsletterbridge\Site\Controller;
defined('_JEXEC') or die;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use SuperSoft\Component\Newsletterbridge\Site\Service\Bridge;

class DisplayController extends BaseController
{
    protected $default_view = 'newsletters';
    public function display($cachable = false, $urlparams = [])
    {
        try {
            $view = $this->input->getCmd('view', 'newsletters');
            if (!in_array($view, ['newsletters','newsletter','preview','send','archive','issue','publications','publication'], true)
                || $this->input->getCmd('layout', 'default') !== 'default') {
                throw new \RuntimeException('COM_NEWSLETTERBRIDGE_NOT_FOUND', 404);
            }
            if (in_array($view, ['archive','issue'], true)) { \SuperSoft\Component\Newsletterbridge\Site\Service\Dependency::factory(); }
            else { new Bridge(); }
            $this->app->allowCache(false);
            $this->app->setHeader('Cache-Control', 'private, no-store', true);
            return parent::display(false, $urlparams);
        } catch (\RuntimeException $e) {
            if (strpos($e->getMessage(), 'COM_NEWSLETTERBRIDGE_') === 0) {
                throw new \RuntimeException(Text::_($e->getMessage()), $e->getCode(), $e);
            }
            throw $e;
        }
    }
    public function execute($task)
    {
        if ($task !== 'display' && $task !== '') { throw new \RuntimeException(Text::_('COM_NEWSLETTERBRIDGE_NOT_FOUND'), 404); }
        return parent::execute('display');
    }
}
