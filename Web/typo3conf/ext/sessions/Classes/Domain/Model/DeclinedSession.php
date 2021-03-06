<?php
namespace TYPO3\Sessions\Domain\Model;


class DeclinedSession extends AnySession
{
    /**
     * @return array
     */
    public function jsonSerialize()
    {
        return [
            '__identity' => $this->uid,
            'title' => $this->title,
            'description' => $this->description,
            'start' => null,
            'end' => null,
            'speakers'  =>  $this->speakers->toArray(),
            'room' => $this->room ? $this->room->getTitle() : '',
            'highlight' => $this->highlight,
            'links' => [
                'self' => $this->getLink(),
                'route' => $this->getRoute(),
            ]
        ];
    }
}
