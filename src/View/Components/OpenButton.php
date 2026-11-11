<?php

namespace Hibira\LiveChat\View\Components;

use Illuminate\View\Component;

/**
 * <x-hibira-live-chat-open topic="sales" class="btn">Talk to sales</x-hibira-live-chat-open>:
 * a button that opens the chat. topic="sales" marks a new conversation as a
 * sales inquiry.
 */
class OpenButton extends Component
{
    public function __construct(public ?string $topic = null)
    {
    }

    public function render(): string
    {
        return <<<'BLADE'
<button type="button" {{ $attributes->merge(array_filter(['class' => 'hibira-live-chat-open', 'data-topic' => $topic])) }}>{{ $slot->isEmpty() ? __('hibira-live-chat::chat.open') : $slot }}</button>
BLADE;
    }
}
