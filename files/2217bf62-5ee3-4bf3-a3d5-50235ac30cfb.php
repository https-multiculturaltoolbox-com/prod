<?php

namespace App\Http\Livewire;

use Livewire\Component;
use Illuminate\Support\Facades\Http;

class RandomJoke extends Component
{
    public $joke;
    public $type = 'general'; // Default joke type

    public function mount()
    {
        $this->fetchJoke();
    }

    public function fetchJoke()
    {
        $url = "https://official-joke-api.appspot.com/random_joke";

        if ($this->type === 'programming') {
            $url = "https://official-joke-api.appspot.com/jokes/programming/random";
        }

        $response = Http::get($url);

        if ($response->successful()) {
            $jokeData = $response->json();
            if (isset($jokeData[0])) {
                $this->joke = $jokeData[0]; // API returns an array for category jokes
            } else {
                $this->joke = $jokeData;
            }
        } else {
            $this->joke = [
                'setup' => 'Oops! Something went wrong.',
                'punchline' => 'Try again later.',
            ];
        }
    }

    public function render()
    {
        return view('livewire.random-joke');
    }
}
