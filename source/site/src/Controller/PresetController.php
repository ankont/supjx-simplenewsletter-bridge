<?php
namespace SuperSoft\Component\Newsletterbridge\Site\Controller;
defined('_JEXEC') or die;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Response\JsonResponse;
use SuperSoft\Component\Newsletterbridge\Site\Service\Bridge;
use SuperSoft\Component\Newsletterbridge\Site\Service\InputGuard;
class PresetController extends BaseController
{
    public function execute($task)
    {
        $data = null; $message = null; $error = false; $status = 200;
        try {
            if ($task !== 'save') { throw new \RuntimeException('COM_NEWSLETTERBRIDGE_NOT_FOUND', 404); }
            if (!InputGuard::csrf($this->app)) { throw new \RuntimeException('JINVALID_TOKEN', 403); }
            $id = InputGuard::id($this->input->post->get('id', '', 'raw'), true);
            $data = (new Bridge())->savePreset($id, [
                'title' => $this->input->post->get('name', null, 'raw'),
                'subject' => $this->input->post->get('subject', null, 'raw'),
                'body' => $this->input->post->get('body', null, 'raw'),
            ]);
            $message = Text::_('COM_NEWSLETTERBRIDGE_PRESET_SAVED');
        } catch (\Throwable $e) {
            $error = true;
            $status = in_array($e->getCode(), [400,403,404,409,503], true) ? $e->getCode() : 500;
            $key = $e->getMessage();
            $message = Text::_(str_starts_with($key, 'COM_NEWSLETTERBRIDGE_') || $key === 'JINVALID_TOKEN' ? $key : 'COM_NEWSLETTERBRIDGE_PRESET_SAVE_FAILED');
        }
        $this->app->allowCache(false);
        $this->app->setHeader('status', (string) $status, true);
        $this->app->setHeader('Cache-Control', 'private, no-store', true);
        $this->app->setHeader('Content-Type', 'application/json; charset=utf-8', true);
        $this->app->setHeader('X-Content-Type-Options', 'nosniff', true);
        $this->app->sendHeaders();
        echo new JsonResponse($data, $message, $error, true);
        $this->app->close();
    }
}
