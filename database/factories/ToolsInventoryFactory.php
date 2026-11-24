<?php

namespace Database\Factories;

use App\Models\ToolsInventory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ToolsInventory>
 */
class ToolsInventoryFactory extends Factory
{
    protected $model = ToolsInventory::class;

    public function definition(): array
    {
        $step = $this->faker->numberBetween(1, 6).'#';
        $yesName = $this->faker->randomElement(['COK', 'CUK', 'KOP', 'PPN', 'DIE', 'DSP']);

        return [
            'no' => $this->faker->numberBetween(1, 999),
            'customer' => 'PT. AISIN INDONESIA',
            'machine_name' => 'BF-01 CBP-136',
            'step' => $step,
            'tools_name' => $this->faker->randomElement(['CUT OF KNIFE', 'CHUCK FINGER', 'KO PIN', 'PUNCH PIN', 'DIE']),
            'yes_name' => $yesName,
            'part_name' => 'COLLAR 19.9',
            'tools_name_yes' => sprintf('CBP136 %s %s %s', $step, $yesName, $this->faker->unique()->bothify('PART-####??')),
            'rack_location' => 'D6',
            'part_no' => '983951-09011-C',
            'current_stock' => 4,
            'life_time' => 200000,
            'forecasts' => [410568, 393279, 393279, 393279, 0],
            'forecast_months' => ['2026-11-26', '2026-12-26', '2027-01-27', '2027-02-27', '2027-03-27'],
            'lead_time_days' => 30,
        ];
    }
}
