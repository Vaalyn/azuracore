<?php
namespace Azura\Event;

use Azura\View;
use Symfony\Component\EventDispatcher\Event;

class BuildView extends Event
{
    const NAME = 'build-view';

    protected $view;

    public function __construct(View $view)
    {
        $this->view = $view;
    }

    public function getView(): View
    {
        return $this->view;
    }
}
