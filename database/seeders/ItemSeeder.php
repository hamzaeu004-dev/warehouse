<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Item;
use Illuminate\Database\Seeder;

class ItemSeeder extends Seeder
{
    /**
     * Item list from the manager. Units are a best guess and can be
     * changed from the Items page.
     */
    public function run(): void
    {
        $list = [
            // From the Roosters branch invoice: weighed in kg, counted in pieces/packs
            'Chicken' => [
                'SKIN ON 8 PCS CUT (2.3 - 2.4 GM) (R)' => 'kg',
                'SKIN ON 2 PC CUT 2.3 KG' => 'kg',
                'ZINGER - (110- 130) GM' => 'kg',
            ],
            'Spices' => [
                'Red chilli powder' => 'kg',
                'Crush chilli' => 'kg',
                'Black pepper powder' => 'kg',
                'Black pepper whole' => 'kg',
                'White pepper' => 'kg',
                'Paprika powder' => 'kg',
                'Garam masala powder' => 'kg',
                'Garam masala sabat' => 'kg',
                'Curry powder Shan' => 'packet',
                'Chicken powder' => 'kg',
                'Zeera powder' => 'kg',
                'Zeera' => 'kg',
                'Coriander powder' => 'kg',
                'Knorr cube' => 'packet',
                'Steam rice' => 'kg',
                'Curry patta' => 'packet',
                'Laung' => 'kg',
                'Star anise' => 'kg',
                'Tatri' => 'kg',
                'Haldi' => 'kg',
                'Yellow food color' => 'packet',
                'Dal cheeni' => 'kg',
                'Salt' => 'kg',
                'Green elaichi' => 'kg',
                'Black elaichi' => 'kg',
                'Ajwain' => 'kg',
                'Maida' => 'kg',
                'Kishmish' => 'kg',
                'Corn flour' => 'kg',
                'Oregano' => 'packet',
                'Tikka masala' => 'packet',
            ],
            'Disposable' => [
                'Drink glass' => 'pcs',
                '2oz dips' => 'pcs',
                'Tissue roll' => 'roll',
                'Duster' => 'pcs',
                'Towel' => 'pcs',
                'Black gloves' => 'box',
                'Plastic gloves' => 'packet',
                'Butter paper' => 'roll',
                'Pen' => 'pcs',
                'Stapler pin' => 'box',
                'Tomato ketchup sachet' => 'box',
                'Chilli garlic sachet' => 'box',
                'Printer roll' => 'roll',
                'Cling film roll' => 'roll',
                'Cash machine roll' => 'roll',
                'Washing liquid' => 'litre',
                'Phenyl' => 'litre',
                'Safai brush' => 'pcs',
                'Dry mop' => 'pcs',
                'Expiry roll' => 'roll',
            ],
            'Packing' => [
                'Packing bag 36x48' => 'kg',
                'Packing bag 12x18' => 'kg',
                'Packing bag 8x12' => 'kg',
                'Packing bag 7x9' => 'kg',
                'Packing bag 8x9' => 'kg',
            ],
            'Sauce' => [
                'Tomato ketchup Shezan' => 'bottle',
                'Tomato ketchup' => 'bottle',
                'Sriracha sauce' => 'bottle',
                'Warda classic mayonnaise' => 'bottle',
                'Chilli sauce Shangrila' => 'bottle',
                'Chilli sauce Key brand' => 'bottle',
                'Delish vinegar' => 'bottle',
                'Shangrila vinegar' => 'bottle',
                'Apple vinegar' => 'bottle',
                'White vinegar' => 'bottle',
                'Vinegar' => 'bottle',
                'Honey' => 'bottle',
                'French mustard' => 'bottle',
                'Mustard' => 'bottle',
                'Frying oil' => 'litre',
                'Lemon juice' => 'bottle',
                'Pari pari sauce' => 'bottle',
                'Worcestershire sauce' => 'bottle',
                'Milk' => 'litre',
                'Yogurt' => 'kg',
            ],
            // New items added from the purchase screen land here
            'Other' => [],
        ];

        $order = 1;
        foreach ($list as $categoryName => $items) {
            $category = Category::updateOrCreate(['name' => $categoryName], ['sort_order' => $order++]);

            foreach ($items as $name => $unit) {
                Item::firstOrCreate(
                    ['category_id' => $category->id, 'name' => $name],
                    ['unit' => $unit]
                );
            }
        }
    }
}
