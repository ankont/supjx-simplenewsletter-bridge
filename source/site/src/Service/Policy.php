<?php
namespace SuperSoft\Component\Newsletterbridge\Site\Service;
defined('_JEXEC') or die;

/** Component ACL plus immutable resource ownership. No subscriber capabilities. */
final class Policy
{
    private $user;
    public function __construct($user) { $this->user = $user; }
    public function allows(string $action): bool
    {
        return $this->user && (int) $this->user->id > 0
            && $this->user->authorise($action, 'com_newsletterbridge');
    }
    public function enter(): bool { return $this->allows('newsletterbridge.access'); }
    public function edit(object $item): bool
    {
        return $this->enter() && ($this->allows('core.edit')
            || ($this->allows('core.edit.own') && (int) $item->created_by === (int) $this->user->id));
    }
    public function read(object $item): bool { return $this->edit($item); }
    public function archive(object $item): bool
    {
        return $this->edit($item) && $this->allows('newsletterbridge.archive');
    }
    public function send(object $item): bool
    {
        return $this->read($item) && $this->allows('newsletterbridge.send')
            && $this->user->authorise('simplenewsletter.send', 'com_simplenewsletter');
    }
    public function job(object $job, object $item): bool
    {
        return $this->send($item) && (int) $job->template_id === (int) $item->id
            && ($this->allows('core.edit') || (int) $job->created_by === (int) $this->user->id);
    }
    public function require(bool $allowed): void
    {
        if (!$allowed) { throw new \RuntimeException('COM_NEWSLETTERBRIDGE_FORBIDDEN', 403); }
    }
}
