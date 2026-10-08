<?php
namespace SuperSoft\Component\Newsletterbridge\Site\Controller;
defined('_JEXEC') or die;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Response\JsonResponse;
use SuperSoft\Component\Newsletterbridge\Site\Service\Bridge;
use SuperSoft\Component\Newsletterbridge\Site\Service\InputGuard;

class SendController extends BaseController
{
    /** One explicit dispatch boundary; helpers and inherited tasks cannot be routed. */
    public function execute($task)
    {
        $data = null; $message = null; $error = false; $status = 200;
        try {
            if (!in_array($task, ['start','process','resume','cancel','status','test','preset'], true)) { throw new \RuntimeException('COM_NEWSLETTERBRIDGE_NOT_FOUND', 404); }
            if (!InputGuard::csrf($this->app)) { throw new \RuntimeException('JINVALID_TOKEN', 403); }
            $bridge = new Bridge();
            $jobTask = in_array($task, ['process','resume','cancel','status'], true);
            $id = InputGuard::id($this->input->post->get($jobTask ? 'job_id' : 'id', '', 'raw'), $task === 'preset');
            switch ($task) {
                case 'start': $data = $bridge->start($id, $this->input->post->getString('confirmed') === '1'); break;
                case 'process': case 'resume': $data = $bridge->process($id); break;
                case 'cancel': $data = $bridge->cancel($id); break;
                case 'status': $data = $bridge->status($id); break;
                case 'test':
                    $bridge->test($id, $this->input->post->getString('email'));
                    $data = ['ok' => true]; $message = Text::_('COM_NEWSLETTERBRIDGE_TEST_SENT'); break;
                case 'preset': $data = $bridge->preset($id, $this->input->post->getString('preset')); break;
            }
        } catch (\Throwable $e) {
            $error = true;
            $status = in_array($e->getCode(), [400,403,404,409,503], true) ? $e->getCode() : 500;
            $key = $e->getMessage();
            $message = Text::_(strpos($key, 'COM_NEWSLETTERBRIDGE_') === 0 || $key === 'JINVALID_TOKEN' ? $key : 'COM_NEWSLETTERBRIDGE_SEND_FAILED');
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
