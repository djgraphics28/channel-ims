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
                //update product
                $product->update([
                    'stock' => $oldProduct->stock,
                    'buying_price' => $oldProduct->buyingPrice,
                    'selling_price' => $oldProduct->sellingPrice,
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
                    'category_id' => 1,
                    'unit_id' => 1,
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
}
