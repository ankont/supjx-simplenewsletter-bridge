<?php
namespace SuperSoft\Component\Newsletterbridge\Site\Controller;
defined('_JEXEC') or die;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Router\Route;
use SuperSoft\Component\Newsletterbridge\Site\Service\Archive;
use SuperSoft\Component\Newsletterbridge\Site\Service\InputGuard;
class PublicationController extends BaseController
{
    public function execute($task)
    {
        if (!in_array($task, ['save','publish','withdraw'], true)) { throw new \RuntimeException(Text::_('COM_NEWSLETTERBRIDGE_NOT_FOUND'), 404); }
        try {
            if (!InputGuard::csrf($this->app)) { throw new \RuntimeException('JINVALID_TOKEN', 403); }
            $post = $this->input->post;
            $id = InputGuard::id($post->get('id', '', 'raw'));
            $revision = InputGuard::id($post->get('revision', '', 'raw'), true);
            $archive = new Archive();
            if ($task === 'withdraw') { $archive->withdraw($id, $revision); }
            else {
                $archive->save($id, $revision, $post->get('jform', [], 'array'), $task === 'publish', $post->get('reviewed', '', 'raw') === '1');
            }
            $this->app->enqueueMessage(Text::_('COM_NEWSLETTERBRIDGE_ARCHIVE_SAVED'));
            $itemid = $this->input->getInt('Itemid');
            $this->setRedirect(Route::_('index.php?option=com_newsletterbridge&view=publication&id=' . $id . ($itemid > 0 ? '&Itemid=' . $itemid : ''), false));
            return true;
        } catch (\RuntimeException $e) {
            if ($e->getMessage() === 'JINVALID_TOKEN' || str_starts_with($e->getMessage(), 'COM_NEWSLETTERBRIDGE_')) { throw new \RuntimeException(Text::_($e->getMessage()), $e->getCode(), $e); }
            throw new \RuntimeException(Text::_('COM_NEWSLETTERBRIDGE_LOAD_FAILED'), 500);
        }
    }
}
