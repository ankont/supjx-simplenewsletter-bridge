<?php
namespace SuperSoft\Component\Newsletterbridge\Site\Controller;
defined('_JEXEC') or die;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Router\Route;
use SuperSoft\Component\Newsletterbridge\Site\Service\Bridge;
use SuperSoft\Component\Newsletterbridge\Site\Service\InputGuard;

class NewsletterController extends BaseController
{
    public function execute($task)
    {
        if (!in_array($task, ['edit','save','cancel','state'], true)) { throw new \RuntimeException(Text::_('COM_NEWSLETTERBRIDGE_NOT_FOUND'), 404); }
        if (!InputGuard::csrf($this->app)) {
            throw new \RuntimeException(Text::_('JINVALID_TOKEN'), 403);
        }
        try { return parent::execute($task); }
        catch (\Throwable $e) {
            $key = strpos($e->getMessage(), 'COM_NEWSLETTERBRIDGE_') === 0 ? $e->getMessage() : 'COM_NEWSLETTERBRIDGE_SAVE_FAILED';
            $id = $this->input->post->getInt('id', 0);
            // No raw exception or database values are reflected into author pages.
            if ($task === 'save') {
                $this->app->enqueueMessage(Text::_($key), 'error');
                $this->setRedirect(Route::_($this->url('newsletter', max(0, $id)), false));
                return false;
            }
            throw new \RuntimeException(Text::_($key), in_array($e->getCode(), [400,403,404,409,503], true) ? $e->getCode() : 500);
        }
    }
    private function url(string $view, int $id = 0): string
    {
        return 'index.php?option=com_newsletterbridge&view=' . $view . ($id ? '&id=' . $id : '')
            . ($this->input->getInt('Itemid') > 0 ? '&Itemid=' . $this->input->getInt('Itemid') : '');
    }
    private function resourceId(bool $new = false): int
    {
        return InputGuard::id($this->input->post->get('id', '', 'raw'), $new);
    }
    private function back(): void
    {
        $fallback = $this->url('newsletters');
        $return = InputGuard::returnUrl($this->input->post->getString('return', ''), $fallback);
        $this->setRedirect(Route::_($return, false));
    }
    public function edit()
    {
        $bridge = new Bridge();
        $id = $this->resourceId();
        $bridge->checkout($id);
        $this->setRedirect(Route::_($this->url('newsletter', $id), false));
    }
    public function save()
    {
        $bridge = new Bridge();
        $id = $this->resourceId(true);
        $raw = $this->input->post->get('jform', [], 'array');
        // Preserve entered fields after validation failure; never accept metadata or ownership.
        $this->app->setUserState('com_newsletterbridge.edit.' . $id, array_intersect_key($raw, array_flip(['title','subject','body','published'])));
        $model = $this->getModel('Newsletter', 'Site', ['ignore_request' => true]);
        $form = $model->getForm([], false);
        if (!$form || !$model->validate($form, $raw)) { throw new \RuntimeException('COM_NEWSLETTERBRIDGE_INVALID', 400); }
        $bridge->save($id, $raw);
        $this->app->setUserState('com_newsletterbridge.edit.' . $id, null);
        $this->app->enqueueMessage(Text::_('COM_NEWSLETTERBRIDGE_SAVED'));
        $this->back();
    }
    public function cancel()
    {
        $bridge = new Bridge();
        $id = $this->resourceId(true);
        if ($id) { $bridge->checkin($id); }
        else { $bridge->policy->require($bridge->policy->allows('core.create')); }
        $this->app->setUserState('com_newsletterbridge.edit.' . $id, null);
        $this->back();
    }
    public function state()
    {
        $bridge = new Bridge();
        $bridge->changeState($this->resourceId(), $this->input->post->get('published', '', 'raw'));
        $this->back();
    }
}
