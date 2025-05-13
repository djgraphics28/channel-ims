<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ProductUpdaterSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $oldProducts = \App\Models\OldProduct::all();
        $total = $oldProducts->count();
        $current = 0;

        foreach ($oldProducts as $oldProduct) {
            $current++;
            $this->command->info("Processing product {$current} of {$total}");

            //check if product already exists
            $product = \App\Models\Product::where('code', $oldProduct->code)->first();
            if ($product) {

                //get the unit id base on the unit name

                //update product
                $product->update([
                    'stock' => $oldProduct->stock,
                    'buying_price' => $oldProduct->buyingPrice,
                    'selling_price' => $oldProduct->sellingPrice,
                    'category_id' => $oldProduct->idCategory ?? 0,
                    'unit_id' =>  $this->getBestMatchingUnitId($oldProduct->unit ?? '')
                ]);

                $product->pstocks()->where('branch_id', 1)->update([
                    'stock' => $oldProduct->stock
                ]);

                $this->command->line("Updated product: {$product->name}");
            } else {
                //create product
                $product = \App\Models\Product::create([
                    'name' => $oldProduct->name ?? $oldProduct->description ?? 'Product-' . $oldProduct->code,
                    'code' => $oldProduct->code,
                    'stock' => $oldProduct->stock,
                    'buying_price' => $oldProduct->buyingPrice,
                    'selling_price' => $oldProduct->sellingPrice,
                    'category_id' => 0,
                    'unit_id' =>  $this->getBestMatchingUnitId($oldProduct->unit ?? ''),
                ]);

                $product->pstocks()->create([
                    'branch_id' => 1,
                    'stock' => $oldProduct->stock
                ]);

                $this->command->line("Created new product: {$product->name}");
            }
        }

        $this->command->info("Completed processing {$total} products");
    }

    private function normalizeUnit($unit) {
        return strtolower(
            trim(
                preg_replace('/[^a-z0-9\s]/', '', $unit)
            )
        );
    }

    private function getBestMatchingUnitId($rawUnitName) {
        $normalizedInput = $this->normalizeUnit($rawUnitName);

        $units = \App\Models\Unit::all();

        $bestMatchId = 1; // default to ID 1 if not found
        $shortestDistance = null;

        foreach ($units as $unit) {
            $normalizedUnit = $this->normalizeUnit($unit->name);

            $levDistance = levenshtein($normalizedInput, $normalizedUnit);

            if ($levDistance === 0) {
                return $unit->id; // exact match
            }

            if (is_null($shortestDistance) || $levDistance < $shortestDistance) {
                $shortestDistance = $levDistance;
                $bestMatchId = $unit->id;
            }
        }

        // Optional: threshold limit (e.g. 3)
        return $shortestDistance <= 3 ? $bestMatchId : 1;
    }
}
