<?php
namespace SuperSoft\Component\Newsletterbridge\Administrator\Controller;
defined('_JEXEC') or die;

trait AdministratorAccess
{
    public function execute($task)
    {
        if (!$this->app->getIdentity()->authorise('core.manage', 'com_newsletterbridge')) {
            throw new \RuntimeException(\Joomla\CMS\Language\Text::_('COM_NEWSLETTERBRIDGE_FORBIDDEN'), 403);
        }
        return parent::execute($task);
    }
}
