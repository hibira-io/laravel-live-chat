<?php

namespace Hibira\LiveChat\View\Components;

use Hibira\LiveChat\LiveChat as Chat;
use Illuminate\View\Component;

/** <x-hibira-live-chat />: the chat on this page. Put it before </body> in your layout. */
class LiveChat extends Component
{
    public function __construct(
        public ?string $color = null,
        public ?bool $launcher = null,
        public ?string $lang = null,
        public ?string $nonce = null,
    ) {
    }

    public function render(): string
    {
        return (string) app(Chat::class)->render(null, array_filter([
            'color' => $this->color,
            'launcher' => $this->launcher,
            'lang' => $this->lang,
            'nonce' => $this->nonce,
        ], fn ($v) => $v !== null));
    }
}
