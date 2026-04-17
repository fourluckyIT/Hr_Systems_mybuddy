<?php

namespace Database\Seeders;

use App\Models\Game;
use Illuminate\Database\Seeder;

class GameSeeder extends Seeder
{
    public function run(): void
    {
        $games = [
            ['game_name' => 'Minecraft', 'is_active' => true],
            ['game_name' => 'Roblox', 'is_active' => true],
            ['game_name' => 'GTA V', 'is_active' => true],
            ['game_name' => 'Valorant', 'is_active' => true],
            ['game_name' => 'Other', 'is_active' => true],
        ];

        foreach ($games as $game) {
            Game::create($game);
        }
    }
}
