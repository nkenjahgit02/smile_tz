<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Brand;
use App\Models\Category;
use App\Models\CategoryThreshold;
use App\Models\Coupon;
use App\Models\Customer;
use App\Models\Layaway;
use App\Models\LoyaltyReward;
use App\Models\ParkedSale;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Setting;
use App\Models\Shift;
use App\Models\Stock;
use App\Models\Unit;
use App\Models\User;
use App\Support\CustomerAccountService;
use App\Support\StockService;
use Carbon\CarbonInterface;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DemoDataSeeder extends Seeder
{
    /** Demo sales per calendar year (current year stops at today). */
    private const SALES_PER_YEAR = [
        2024 => 1000,
        2025 => 1000,
        2026 => 1000,
    ];

    private const BRANCH_WEIGHTS = [
        'MAIN' => 50,
        'KAR' => 30,
        'MIK' => 20,
    ];

    private const PAYMENT_POOL = [
        'cash' => 40,
        'mobile' => 22,
        'card' => 16,
        'bank_transfer' => 6,
        'loyalty' => 6,
        'credit' => 6,
        'deposit' => 4,
    ];

    private array $branches = [];

    private array $branchById = [];

    private array $sellers = [];

    private array $products = [];

    private array $customers = [];

    private array $coupons = [];

    private array $rewards = [];

    private array $shifts = [];

    private array $salesByCustomer = [];

    private int $saleCounter = 0;

    private int $receiptCounter = 0;

    public function run(): void
    {
        if (Setting::where('key', 'demo_seeded_at')->exists()) {
            $this->command?->warn('Demo data already seeded. Reset the database first (migrate:fresh --seed) or delete the demo_seeded_at setting key.');

            return;
        }

        DB::transaction(function () {
            $this->seedBranches();
            $this->seedCatalog();
            $this->seedStock();
            $this->seedUsers();
            $this->seedCustomers();
            $this->seedLoyalty();
            $this->seedCoupons();
            $this->seedShifts();
            $this->seedSales();
            $this->seedLayaways();
            $this->finaliseCustomerAccounts();
            $this->seedParkedSales();
            $this->seedCategoryThresholds();
            $this->seedStockAlerts();
            $this->seedActivity();
        });

        Setting::updateOrCreate(['key' => 'demo_seeded_at'], [
            'value' => now()->toDateTimeString(),
            'group' => 'demo',
        ]);

        $this->command?->info(sprintf(
            'Seeded %d sales, %d customers, %d products, %d coupons across %d branches.',
            Sale::count(),
            Customer::count(),
            Product::count(),
            Coupon::count(),
            count($this->branches),
        ));
    }

    private function seedBranches(): void
    {
        $main = Branch::firstOrCreate(['code' => 'MAIN'], [
            'name' => 'Main Branch',
            'type' => 'godown',
        ]);
        $main->forceFill(['type' => 'godown'])->save();

        foreach (['MAIN' => 'Main Branch', 'KAR' => 'Kariakoo', 'MIK' => 'Mikocheni'] as $code => $name) {
            $branch = Branch::firstOrCreate(
                ['code' => $code],
                [
                    'name' => $name,
                    'address' => $name.' Area, Dar es Salaam',
                    'phone' => '+255 700 000 00'.count($this->branches),
                    'email' => strtolower($code).'@example.com',
                    'currency' => 'TZS',
                    'is_active' => true,
                    'type' => $code === 'MAIN' ? 'godown' : 'store',
                    'parent_id' => $code === 'MAIN' ? null : $main->id,
                ],
            );

            if ($code === 'MAIN') {
                $branch->forceFill(['type' => 'godown', 'parent_id' => null])->save();
            } else {
                $branch->forceFill(['type' => 'store', 'parent_id' => $main->id])->save();
            }

            $this->branches[$code] = $branch;
            $this->branchById[$branch->id] = $branch;
        }
    }

    private function seedCatalog(): void
    {
        $categories = ['Grocery', 'Beverages', 'Dairy', 'Household', 'Personal Care', 'Snacks'];
        $brands = ['Dove', 'Nivea', 'Colgate', 'Ariel', 'Bic', 'Kiri', 'Tang', 'Azam'];

        foreach ($categories as $name) {
            Category::firstOrCreate(['name' => $name], ['is_active' => true]);
        }
        foreach ($brands as $name) {
            Brand::firstOrCreate(['name' => $name], ['is_active' => true]);
        }

        $units = [
            'Piece' => 'pcs',
            'Kilogram' => 'kg',
            'Litre' => 'ltr',
            'Box' => 'box',
            'Dozen' => 'dz',
        ];
        foreach ($units as $name => $abbr) {
            Unit::firstOrCreate(['name' => $name], ['abbreviation' => $abbr, 'is_active' => true]);
        }

        $categoriesMap = Category::pluck('id', 'name');
        $brandsMap = Brand::pluck('id', 'name');
        $unitsMap = Unit::pluck('id', 'name');

        $items = [
            ['name' => 'Dove Shampoo 250ml', 'sku' => 'DVSMP250', 'category' => 'Personal Care', 'brand' => 'Dove', 'unit' => 'Piece', 'cost' => 4200, 'price' => 6500, 'reorder' => 20],
            ['name' => 'Nivea Body Lotion 200ml', 'sku' => 'NVBL200', 'category' => 'Personal Care', 'brand' => 'Nivea', 'unit' => 'Piece', 'cost' => 5100, 'price' => 7800, 'reorder' => 20],
            ['name' => 'Colgate Toothpaste 100g', 'sku' => 'CGTP100', 'category' => 'Personal Care', 'brand' => 'Colgate', 'unit' => 'Piece', 'cost' => 2100, 'price' => 3500, 'reorder' => 40],
            ['name' => 'Ariel Detergent 1kg', 'sku' => 'ARLDT1', 'category' => 'Household', 'brand' => 'Ariel', 'unit' => 'Kilogram', 'cost' => 6300, 'price' => 9500, 'reorder' => 30],
            ['name' => 'Bic Ballpoint Pen (box)', 'sku' => 'BICBP1', 'category' => 'Household', 'brand' => 'Bic', 'unit' => 'Box', 'cost' => 2400, 'price' => 4200, 'reorder' => 50],
            ['name' => 'Kiri Cheese Cream 150g', 'sku' => 'KIRCH150', 'category' => 'Dairy', 'brand' => 'Kiri', 'unit' => 'Piece', 'cost' => 3400, 'price' => 5400, 'reorder' => 25],
            ['name' => 'Tang Orange Juice 2L', 'sku' => 'TNGJU2', 'category' => 'Beverages', 'brand' => 'Tang', 'unit' => 'Litre', 'cost' => 4600, 'price' => 7200, 'reorder' => 25],
            ['name' => 'Azam Nafasi Rice 5kg', 'sku' => 'AZMRC5', 'category' => 'Grocery', 'brand' => 'Azam', 'unit' => 'Kilogram', 'cost' => 15000, 'price' => 21000, 'reorder' => 15],
            ['name' => 'Azam Wheat Flour 2kg', 'sku' => 'AZMWF2', 'category' => 'Grocery', 'brand' => 'Azam', 'unit' => 'Kilogram', 'cost' => 4200, 'price' => 6200, 'reorder' => 30],
            ['name' => 'Cooking Oil 1L', 'sku' => 'COKL1', 'category' => 'Grocery', 'brand' => 'Azam', 'unit' => 'Litre', 'cost' => 3800, 'price' => 5900, 'reorder' => 40],
            ['name' => 'Sugar 1kg', 'sku' => 'SUGR1', 'category' => 'Grocery', 'brand' => 'Azam', 'unit' => 'Kilogram', 'cost' => 2600, 'price' => 4200, 'reorder' => 50],
            ['name' => 'Milk Longlife 1L', 'sku' => 'MLKLF1', 'category' => 'Dairy', 'brand' => 'Azam', 'unit' => 'Litre', 'cost' => 1800, 'price' => 2900, 'reorder' => 60],
            ['name' => 'Mineral Water 1.5L', 'sku' => 'WTR15', 'category' => 'Beverages', 'brand' => 'Azam', 'unit' => 'Litre', 'cost' => 900, 'price' => 1800, 'reorder' => 80],
            ['name' => 'Bread Loaf 400g', 'sku' => 'BRDLF4', 'category' => 'Grocery', 'brand' => 'Azam', 'unit' => 'Piece', 'cost' => 1500, 'price' => 2600, 'reorder' => 40],
            ['name' => 'Dozen Eggs', 'sku' => 'EGGDZ1', 'category' => 'Grocery', 'brand' => 'Azam', 'unit' => 'Dozen', 'cost' => 5200, 'price' => 7800, 'reorder' => 30],
            ['name' => 'Instant Coffee 100g', 'sku' => 'COF100', 'category' => 'Beverages', 'brand' => 'Azam', 'unit' => 'Piece', 'cost' => 8900, 'price' => 12500, 'reorder' => 15],
            ['name' => 'Maize Meal 2kg', 'sku' => 'MAZM2', 'category' => 'Grocery', 'brand' => 'Azam', 'unit' => 'Kilogram', 'cost' => 3100, 'price' => 4800, 'reorder' => 35],
            ['name' => 'Tea Leaves 250g', 'sku' => 'TEAL250', 'category' => 'Beverages', 'brand' => 'Azam', 'unit' => 'Piece', 'cost' => 2400, 'price' => 3900, 'reorder' => 25],
            ['name' => 'Potato Chips 100g', 'sku' => 'CHPS100', 'category' => 'Snacks', 'brand' => 'Azam', 'unit' => 'Piece', 'cost' => 1100, 'price' => 2200, 'reorder' => 60],
            ['name' => 'Biscuits Assorted 200g', 'sku' => 'BSC200', 'category' => 'Snacks', 'brand' => 'Azam', 'unit' => 'Piece', 'cost' => 1600, 'price' => 2900, 'reorder' => 45],
            ['name' => 'Peanut Butter 500g', 'sku' => 'PNB500', 'category' => 'Snacks', 'brand' => 'Azam', 'unit' => 'Piece', 'cost' => 4800, 'price' => 7400, 'reorder' => 20],
            ['name' => 'Tomato Sauce 500g', 'sku' => 'TOMS500', 'category' => 'Grocery', 'brand' => 'Azam', 'unit' => 'Piece', 'cost' => 1900, 'price' => 3200, 'reorder' => 40],
            ['name' => 'Laundry Soap 200g', 'sku' => 'LNDS200', 'category' => 'Household', 'brand' => 'Ariel', 'unit' => 'Piece', 'cost' => 800, 'price' => 1500, 'reorder' => 70],
            ['name' => 'Air Freshener 275ml', 'sku' => 'AIRF275', 'category' => 'Household', 'brand' => 'Nivea', 'unit' => 'Piece', 'cost' => 2700, 'price' => 4300, 'reorder' => 20],
        ];

        foreach ($items as $index => $item) {
            $product = Product::firstOrCreate(
                ['sku' => $item['sku']],
                [
                    'name' => $item['name'],
                    'category_id' => $categoriesMap[$item['category']],
                    'brand_id' => $brandsMap[$item['brand']],
                    'unit_id' => $unitsMap[$item['unit']],
                    'godown_price' => $item['cost'],
                    'shop_price' => $item['price'],
                    'reorder_level' => $item['reorder'],
                    'is_active' => true,
                ],
            );

            $this->products[] = [
                'id' => $product->id,
                'cost' => $item['cost'],
                'price' => $item['price'],
            ];

            if ($index === 0) {
                $product->update(['barcode' => '200'.Str::padLeft((string) $product->id, 7, '0')]);
            }
        }
    }

    private function seedStock(): void
    {
        $stock = app(StockService::class);

        foreach ($this->branches as $branch) {
            foreach ($this->products as $product) {
                $qty = random_int(200, 2200);
                $stock->restock($branch->id, $product['id'], null, $qty, $product['cost']);
            }
        }
    }

    private function seedCategoryThresholds(): void
    {
        $capacities = Product::query()
            ->whereNotNull('category_id')
            ->selectRaw('category_id, coalesce(sum(reorder_level), 0) as capacity')
            ->groupBy('category_id')
            ->get()
            ->pluck('capacity', 'category_id');

        foreach ($this->branches as $branch) {
            foreach ($capacities as $categoryId => $capacity) {
                CategoryThreshold::firstOrCreate(
                    ['category_id' => $categoryId, 'branch_id' => $branch->id],
                    ['normal_capacity' => $capacity],
                );
            }
        }
    }

    private function seedUsers(): void
    {
        $roles = ['Seller', 'Seller', 'Seller', 'Seller', 'Seller', 'Seller', 'Admin', 'Finance', 'branch-manager', 'branch-manager', 'godown-staff', 'godown-manager'];
        $branchCodes = ['MAIN', 'MAIN', 'KAR', 'KAR', 'MIK', 'MIK', 'MAIN', 'MAIN', 'KAR', 'MIK', 'MAIN', 'MAIN'];
        $firstNames = ['Asha', 'Baraka', 'Zawadi', 'Neema', 'Juma', 'Rehema', 'Salum', 'Mwanaidi', 'Yusuph', 'Halima', 'Didas', 'Ernest'];

        foreach ($roles as $index => $role) {
            $branch = $this->branches[$branchCodes[$index]];
            $user = User::create([
                'name' => $firstNames[$index].' Kassim '.$index,
                'email' => 'demo.'.Str::lower($firstNames[$index]).'@smilelocal.test',
                'phone' => '+255 7'.random_int(10, 99).' 000 0'.Str::padLeft((string) $index, 3, '0'),
                'password' => 'password',
                'branch_id' => $branch->id,
                'is_active' => true,
                'email_verified_at' => now(),
            ]);
            $user->assignRole($role);
            $user->locations()->sync([$branch->id]);

            if ($role === 'Seller') {
                $this->sellers[] = $user;
            }
        }
    }

    private function seedCustomers(): void
    {
        $memberNames = [
            'Amina Fumbuka', 'Bakari Mwinyi', 'Cecilia Kessy', 'Daudi Mahenge', 'Ester Mushi',
            'Faraji Kidunda', 'Grace Melkior', 'Hamisi Ndosi', 'Irene Komba', 'Jabari Mkumbo',
            'Kadija Salehe', 'Lucas Ngowi', 'Mariam Fundi', 'Nelson Mbise', 'Omari Juma',
            'Pendo Shao', 'Queen Ntuli', 'Rashidi Madafu', 'Sophia Mrema', 'Tatu Kijazi',
        ];
        $creditNames = [
            'Abdullah Trading Co', 'Crystal Guest House', 'Delight Canteen', 'Emma\'s Salon', 'Golden Hotel Ltd',
            'Harbour Restaurant', 'Imani School', 'Jambo Café', 'Kilimanjaro Bakers', 'Lighthouse Store',
            'Mzuri Minimart', 'Neema Chemists', 'Oceanview Bar', 'Pambo Superstore', 'Qatar Supplies Ltd',
            'Regency Lodge', 'Sunrise Butchery', 'Talanta SIACO', 'Unity Guest Inn', 'Viva Nursery School',
        ];

        $names = array_merge($memberNames, $creditNames, ['Walk-In Guest', 'Casual Shopper', 'Tourist Visitor']);

        foreach ($names as $index => $name) {
            $branch = $this->pickBranch();
            $type = 'walk-in';
            if ($index < count($memberNames)) {
                $type = 'member';
            } elseif ($index < count($memberNames) + count($creditNames)) {
                $type = 'credit';
            }

            $createdAt = $this->randomDate(2024, now()->subMonths(2)->year);

            $customer = Customer::create([
                'branch_id' => $branch->id,
                'customer_no' => 'CUS-'.(1000 + $index + 1),
                'name' => $name,
                'phone' => '+255 7'.random_int(10, 99).' 000 '.Str::padLeft((string) ($index % 100), 3, '0'),
                'email' => strtolower(Str::slug($name)).'@example.com',
                'address' => $branch->name.', Dar es Salaam',
                'customer_type' => $type,
                'is_active' => true,
                'credit_limit' => $type === 'credit' ? random_int(300_000, 2_500_000) : 0,
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);

            $this->customers[$customer->id] = [
                'model' => $customer,
                'type' => $type,
                'branch_id' => $branch->id,
                'credit_used' => 0,
                'credit_limit' => (float) $customer->credit_limit,
                'deposits' => 0,
                'earned' => 0,
            ];
        }
    }

    private function seedLoyalty(): void
    {
        $owner = User::role('Owner')->first();
        $definitions = [
            ['name' => 'Points Voucher 100', 'points_cost' => 100, 'type' => 'points', 'value' => 0, 'min_tier' => 'bronze'],
            ['name' => 'TZS 2,000 Discount', 'points_cost' => 200, 'type' => 'fixed', 'value' => 2000, 'min_tier' => 'silver'],
            ['name' => '5% Order Discount', 'points_cost' => 300, 'type' => 'percentage', 'value' => 5, 'min_tier' => 'silver'],
            ['name' => '10% Order Discount', 'points_cost' => 500, 'type' => 'percentage', 'value' => 10, 'min_tier' => 'gold'],
            ['name' => 'Free Delivery Voucher', 'points_cost' => 400, 'type' => 'fixed', 'value' => 8000, 'min_tier' => 'gold'],
            ['name' => 'TZS 10,000 Luxury Voucher', 'points_cost' => 1000, 'type' => 'fixed', 'value' => 10000, 'min_tier' => 'platinum'],
        ];

        foreach ($definitions as $reward) {
            $this->rewards[] = LoyaltyReward::create([...$reward, 'is_active' => true, 'created_by' => $owner?->id]);
        }
    }

    private function seedCoupons(): void
    {
        $coupons = [
            ['code' => 'WELCOME10', 'type' => 'percentage', 'value' => 10, 'min_spend' => 5000, 'max_discount' => 4000],
            ['code' => 'FLASH2000', 'type' => 'fixed', 'value' => 2000, 'min_spend' => 20000],
            ['code' => 'NEWYEAR-2024', 'type' => 'percentage', 'value' => 15, 'min_spend' => 15000, 'max_discount' => 9000],
            ['code' => 'EASTER-2025', 'type' => 'percentage', 'value' => 12, 'min_spend' => 12000, 'max_discount' => 7000],
            ['code' => 'MAMA-TZ-10', 'type' => 'percentage', 'value' => 10, 'min_spend' => 8000, 'max_discount' => 5000],
            ['code' => 'LALA-SALEM-5', 'type' => 'fixed', 'value' => 5000, 'min_spend' => 25000],
            ['code' => 'RICE-DEAL', 'type' => 'fixed', 'value' => 1500, 'min_spend' => 10000],
            ['code' => 'LOYAL-MEMBER', 'type' => 'percentage', 'value' => 8, 'min_spend' => 20000, 'max_discount' => 8000],
        ];

        foreach ($coupons as $coupon) {
            $this->coupons[] = Coupon::create([
                ...$coupon,
                'usage_limit' => null,
                'used_count' => 0,
                'is_active' => true,
                'valid_from' => Carbon::create(2024, 1, 1),
                'valid_to' => now()->addMonths(6),
            ]);
        }
    }

    private function seedShifts(): void
    {
        foreach ($this->branches as $branch) {
            foreach ([2024, 2025] as $year) {
                for ($month = 1; $month <= 12; $month++) {
                    $opened = Carbon::create($year, $month, random_int(1, 5), 8, random_int(0, 59));
                    $this->createShift($branch, $opened, now()->subMonths(3));
                }
            }

            foreach ([2026] as $year) {
                for ($month = 1; $month <= now()->month; $month++) {
                    if ($year === 2026 && $month > now()->month) {
                        continue;
                    }
                    $opened = Carbon::create($year, $month, random_int(1, 5), 8, random_int(0, 59));
                    $this->createShift($branch, $opened, now());
                }
            }

            $this->createShift($branch, now()->startOfDay()->setTime(8, random_int(0, 30)), null, 'open');
        }
    }

    private function createShift(Branch $branch, CarbonInterface $opened, ?CarbonInterface $lastAllowed, string $status = 'closed'): void
    {
        if ($lastAllowed && $opened->gt($lastAllowed)) {
            return;
        }

        $openingFloat = random_int(100_000, 300_000);
        $expected = $openingFloat + random_int(400_000, 2_200_000);
        $closed = $opened->copy()->setTime(21, random_int(0, 59));

        $shift = Shift::create([
            'branch_id' => $branch->id,
            'user_id' => $this->sellers[array_rand($this->sellers)]->id,
            'shift_no' => 'SHIFT-'.$opened->format('Ymd').'-'.$branch->code,
            'opening_float' => $openingFloat,
            'closing_count' => $status === 'closed' ? $expected + random_int(-8000, 8000) : null,
            'expected_in_drawer' => $status === 'closed' ? $expected : null,
            'difference' => $status === 'closed' ? random_int(-3000, 3000) : null,
            'status' => $status,
            'opened_at' => $opened,
            'closed_at' => $status === 'closed' ? $closed : null,
            'created_at' => $opened,
            'updated_at' => $closed,
        ]);

        $this->shifts[$branch->id][$opened->format('Y-n')] = $shift->id;
    }

    private function seedSales(): void
    {
        $itemsBatch = [];
        $paymentsBatch = [];
        $couponRowsBatch = [];
        $couponUsage = array_fill_keys(array_map(fn ($c) => $c->id, $this->coupons), 0);

        foreach (self::SALES_PER_YEAR as $year => $count) {
            for ($i = 0; $i < $count; $i++) {
                $createdAt = $this->randomSaleTimestamp($year);
                $branch = $this->pickBranch();

                [$items, $subtotal, $customer] = $this->buildSaleItems($branch);

                $coupon = null;
                $couponDiscount = 0;
                if (random_int(1, 100) <= 8) {
                    $coupon = $this->coupons[array_rand($this->coupons)];
                    $candidate = min($coupon->discountFor($subtotal), $coupon->max_discount ?? PHP_FLOAT_MAX);
                    if ($candidate > 0) {
                        $couponDiscount = round($candidate, 2);
                    }
                }

                $discountTotal = $couponDiscount;
                $total = round($subtotal - $couponDiscount, 2);
                $payments = $this->buildPayments($total, $customer);

                $amountPaid = array_sum(array_column($payments, 'amount'));
                $change = 0.0;
                $amountPaid = min(round($amountPaid, 2), $total);

                $statusRoll = random_int(1, 1000);
                $status = 'active';
                $voidedBy = null;
                $voidReason = null;
                if ($statusRoll <= 8) {
                    $status = 'returned';
                } elseif ($statusRoll <= 18) {
                    $status = 'voided';
                    $voidedBy = $this->sellers[array_rand($this->sellers)]->id;
                    $voidReason = 'Customer changed mind / demo reversal';
                }

                $this->saleCounter++;
                $saleNo = 'SALE-'.$createdAt->format('YmdHis').'-'.Str::padLeft((string) $this->saleCounter, 4, '0');
                $this->receiptCounter++;
                $receiptNo = 'RCP-'.$createdAt->format('YmdHis').'-'.Str::padLeft((string) $this->receiptCounter, 4, '0');

                $shiftId = $this->shifts[$branch->id][$createdAt->format('Y-n')] ?? null;

                $sale = Sale::create([
                    'branch_id' => $branch->id,
                    'user_id' => $this->sellersForBranch($branch)['id'],
                    'customer_id' => $customer ? $customer['model']->id : null,
                    'shift_id' => $shiftId,
                    'sale_no' => $saleNo,
                    'receipt_no' => $receiptNo,
                    'subtotal' => $subtotal,
                    'discount_total' => $discountTotal,
                    'tax_total' => 0,
                    'total' => $total,
                    'amount_paid' => $amountPaid,
                    'change_amount' => $change,
                    'status' => $status,
                    'payment_status' => $amountPaid >= $total ? 'paid' : 'partial',
                    'voided_by' => $voidedBy,
                    'void_reason' => $voidReason,
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt,
                ]);

                foreach ($items as $item) {
                    $itemsBatch[] = [
                        'sale_id' => $sale->id,
                        'product_id' => $item['product_id'],
                        'product_variant_id' => null,
                        'quantity' => $item['quantity'],
                        'original_unit_price' => $item['original'],
                        'unit_price' => $item['price'],
                        'discount' => $item['discount'],
                        'line_total' => $item['line_total'],
                        'cost_price' => $item['cost'],
                        'profit' => $item['profit'],
                        'is_loss' => false,
                        'loss_reason' => null,
                        'price_overridden' => false,
                        'price_override_reason' => null,
                        'price_override_approved_by' => null,
                        'created_at' => $createdAt,
                        'updated_at' => $createdAt,
                    ];
                }

                foreach ($payments as $payment) {
                    $paymentsBatch[] = [
                        'sale_id' => $sale->id,
                        'method' => $payment['method'],
                        'amount' => $payment['amount'],
                        'reference' => in_array($payment['method'], ['card', 'mobile'], true) ? 'TX'.Str::upper(Str::random(10)) : null,
                        'received_by' => $this->sellersForBranch($branch)['id'],
                        'created_at' => $createdAt,
                        'updated_at' => $createdAt,
                    ];
                }

                if ($coupon && $couponDiscount > 0) {
                    $couponRowsBatch[] = [
                        'sale_id' => $sale->id,
                        'coupon_id' => $coupon->id,
                        'discount_amount' => $couponDiscount,
                        'created_at' => $createdAt,
                        'updated_at' => $createdAt,
                    ];
                    $couponUsage[$coupon->id]++;
                }

                $this->trackCustomerSale($customer, $sale, $payments, $createdAt);
            }
        }

        foreach (array_chunk($itemsBatch, 500) as $chunk) {
            DB::table('sale_items')->insert($chunk);
        }
        foreach (array_chunk($paymentsBatch, 500) as $chunk) {
            DB::table('payments')->insert($chunk);
        }
        foreach (array_chunk($couponRowsBatch, 500) as $chunk) {
            DB::table('sale_coupons')->insert($chunk);
        }

        foreach ($couponUsage as $couponId => $used) {
            Coupon::where('id', $couponId)->update(['used_count' => $used]);
        }
    }

    private function buildSaleItems($branch): array
    {
        $count = $this->weightedInt([1 => 55, 2 => 25, 3 => 12, 4 => 6, 5 => 2]);

        // Prefer a known customer for around 55% of baskets.
        $customer = null;
        if (random_int(1, 100) <= 55 && $this->customers !== []) {
            $customer = $this->customers[array_rand($this->customers)];
        }

        $items = [];
        $subtotal = 0.0;
        $picked = [];

        for ($i = 0; $i < $count; $i++) {
            if (count($picked) >= count($this->products)) {
                $picked = [];
            }

            $index = $this->weightedIndex(count($this->products) - 1);
            $attempts = 0;
            while (in_array($index, $picked, true) && $attempts++ < 10) {
                $index = random_int(0, count($this->products) - 1);
            }
            $picked[] = $index;

            $product = $this->products[$index];
            $quantity = $this->weightedInt([1 => 60, 2 => 25, 3 => 10, 4 => 4, 5 => 1]);
            $unitPrice = $product['price'];
            $discount = 0.0;
            if (random_int(1, 100) <= 12) {
                $discount = round($unitPrice * $this->weightedInt([5 => 60, 10 => 40]) / 100, 2);
            }

            $lineTotal = round($unitPrice * $quantity - $discount, 2);
            $subtotal += $lineTotal;

            $items[] = [
                'product_id' => $product['id'],
                'quantity' => $quantity,
                'original' => $unitPrice,
                'price' => $unitPrice,
                'discount' => $discount,
                'line_total' => $lineTotal,
                'cost' => $product['cost'],
                'profit' => round($lineTotal - $product['cost'] * $quantity, 2),
            ];
        }

        return [$items, round($subtotal, 2), $customer];
    }

    private function buildPayments(float $total, ?array $customer): array
    {
        // Seeded customers pay the ordinary ways; credit/deposit methods are reserved
        // for a companion finaliseCustomerAccounts step and the live POS flows.
        $pool = ['cash' => 40, 'mobile' => 25, 'card' => 18, 'bank_transfer' => 8, 'loyalty' => 9];
        $single = $this->weightedPick($pool);
        $payments = [['method' => $single, 'amount' => round($total, 2)]];

        // Occasionally split across two tenders.
        if ($total >= 10_000 && random_int(1, 100) <= 18) {
            $second = $this->weightedPick(array_diff_key($pool, [$single => true]));
            $firstAmount = round($total * random_int(30, 60) / 100, 2);
            $payments = [
                ['method' => $single, 'amount' => $firstAmount],
                ['method' => $second, 'amount' => round($total - $firstAmount, 2)],
            ];
        }

        return $payments;
    }

    private function trackCustomerSale(?array $customer, Sale $sale, array $payments, Carbon $createdAt): void
    {
        if (! $customer) {
            return;
        }

        $paid = round((float) $sale->amount_paid - (float) $sale->change_amount, 2);
        $points = (int) floor(max($paid, 0) / 1000) * CustomerAccountService::POINTS_PER_THOUSAND;

        $this->salesByCustomer[$customer['model']->id][] = [
            'amount_paid' => $paid,
            'total' => (float) $sale->total,
            'points' => $points,
            'branch_id' => $sale->branch_id,
            'sale_no' => $sale->sale_no,
            'sale_id' => $sale->id,
            'created_at' => $createdAt,
        ];
    }

    private function finaliseCustomerAccounts(): void
    {
        $creditsUsed = [];
        $transactionRows = [];

        $memberTally = [];
        foreach ($this->customers as $customerId => $data) {
            if ($data['type'] === 'member') {
                $memberTally[$customerId] = (int) collect($this->salesByCustomer[$customerId] ?? [])->sum('points');
            }
        }
        arsort($memberTally);
        $platinumTargets = array_slice(array_keys($memberTally), 0, 2);

        foreach ($this->customers as $customerId => $data) {
            /** @var Customer $customer */
            $customer = $data['model'];
            $sales = collect($this->salesByCustomer[$customerId] ?? []);

            $lifetimeSpend = round((float) $sales->sum('total'), 2);
            $runningCredit = 0.0;
            $runningDeposit = 0.0;
            $runningPoints = 0;
            $newBalance = null;
            $lastCreditSaleAt = null;

            if ($data['type'] === 'credit') {
                foreach ($sales->sortBy('created_at') as $sale) {
                    if (random_int(1, 100) <= 30) {
                        $runningCredit = round($runningCredit + $sale['amount_paid'], 2);
                        $lastCreditSaleAt = $sale['created_at'];
                        $transactionRows[] = $this->ledgerRow($customer, $sale['branch_id'], 'credit_sale', [
                            'amount' => 0,
                            'running_credit' => $runningCredit,
                            'description' => "Credit sale {$sale['sale_no']}",
                            'reference_type' => 'sale',
                            'reference_id' => $sale['sale_id'],
                            'created_at' => $sale['created_at'],
                        ]);
                    }
                }

                if ($runningCredit > 0) {
                    $collected = 0.0;
                    $parts = random_int(1, 3);
                    for ($part = 0; $part < $parts; $part++) {
                        if ($runningCredit - $collected <= 0) {
                            break;
                        }

                        $installment = round(($runningCredit - $collected) * random_int(25, 100) / 100, 2);
                        if ($installment <= 0) {
                            continue;
                        }

                        $collected = round($collected + $installment, 2);
                        $newBalance = round($runningCredit - $collected, 2);
                        $transactionRows[] = $this->ledgerRow($customer, $this->pickBranch()->id, 'credit_payment', [
                            'amount' => -$installment,
                            'running_credit' => $newBalance,
                            'description' => 'Credit payment received (cash / mobile)',
                            'created_at' => $this->randomDateBetween(
                                $lastCreditSaleAt ?? Carbon::create(2024, 1, 1),
                                now(),
                            )->setTime(random_int(9, 18), random_int(0, 59)),
                        ]);
                    }

                    $customer->credit_balance = $newBalance;
                }

                $customer->credit_limit = max((float) $customer->credit_limit, $runningCredit * 1.6 + 50_000);
            }

            if ($data['type'] === 'member') {
                $events = [];

                foreach ($sales->sortBy('created_at') as $sale) {
                    if ($sale['points'] > 0) {
                        $events[] = ['at' => $sale['created_at'], 'points' => $sale['points'], 'sale' => $sale];
                    }
                }

                $redeemed = 0;
                if (count($events) > 0 && random_int(1, 100) <= 40) {
                    $lastEarnAt = $events[array_key_last($events)]['at'];
                    $redeemed = min((int) round(count($events) ? array_sum(array_column($events, 'points')) * random_int(20, 70) / 100 : 0), array_sum(array_column($events, 'points')));
                    if ($redeemed > 0) {
                        $events[] = ['at' => $this->randomDateBetween($lastEarnAt, now()), 'redeem' => $redeemed];
                    }
                }

                $depositBalance = 0.0;
                if (random_int(1, 100) <= 30) {
                    $totalDeposited = random_int(50_000, 400_000);
                    $spent = random_int(0, (int) ($totalDeposited * 0.6));
                    $depositAt = $this->randomDateBetween(Carbon::create(2025, 1, 1), now());
                    $events[] = ['at' => $depositAt, 'deposit' => $totalDeposited];
                    if ($spent > 0) {
                        $events[] = ['at' => $this->randomDateBetween($depositAt, now()), 'spent' => $spent];
                    }
                    $depositBalance = round($totalDeposited - $spent, 2);
                }

                usort($events, fn ($a, $b) => $a['at'] <=> $b['at']);

                $runningPoints = 0;
                $runningDeposit = 0.0;
                foreach ($events as $event) {
                    if (isset($event['points'])) {
                        $runningPoints += $event['points'];
                        $transactionRows[] = $this->ledgerRow($customer, $event['sale']['branch_id'], 'loyalty_earn', [
                            'points' => $event['points'],
                            'running_points' => $runningPoints,
                            'running_deposit' => $runningDeposit,
                            'description' => "Earned {$event['points']} pts on sale {$event['sale']['sale_no']}",
                            'reference_type' => 'sale',
                            'reference_id' => $event['sale']['sale_id'],
                            'created_at' => $event['at'],
                        ]);
                    } elseif (isset($event['redeem'])) {
                        $runningPoints -= $event['redeem'];
                        $transactionRows[] = $this->ledgerRow($customer, $this->pickBranch($customer->branch_id)->id, 'loyalty_redeem', [
                            'points' => -$event['redeem'],
                            'running_points' => $runningPoints,
                            'running_deposit' => $runningDeposit,
                            'description' => 'Redeemed '.$event['redeem'].' pts for a '.($this->rewards[array_rand($this->rewards)]->name ?? 'reward'),
                            'created_at' => $event['at'],
                        ]);
                    } elseif (isset($event['deposit'])) {
                        $runningDeposit = round((float) $event['deposit'], 2);
                        $transactionRows[] = $this->ledgerRow($customer, $customer->branch_id, 'deposit', [
                            'amount' => 0,
                            'running_deposit' => $runningDeposit,
                            'running_points' => $runningPoints,
                            'description' => 'Wallet top-up (customer cash)',
                            'created_at' => $event['at'],
                        ]);
                    } elseif (isset($event['spent'])) {
                        $runningDeposit = round($runningDeposit - (float) $event['spent'], 2);
                        $transactionRows[] = $this->ledgerRow($customer, $customer->branch_id, 'deposit_reversal', [
                            'amount' => -$event['spent'],
                            'running_deposit' => $runningDeposit,
                            'running_points' => $runningPoints,
                            'description' => 'Deposit applied to purchase',
                            'created_at' => $event['at'],
                        ]);
                    }
                }

                $lifetimePoints = $runningPoints + $redeemed;

                $earnedTotal = array_sum(array_column($events, 'points'));
                if (count($events) > 0) {
                    $needsPlatinum = $earnedTotal >= 1400 || in_array($customerId, $platinumTargets, true);
                    $needsGold = $earnedTotal >= 950 && ! $needsPlatinum;

                    if (random_int(1, 100) <= 35 || $needsPlatinum || $needsGold) {
                        if ($needsPlatinum) {
                            $bonus = max($this->weightedInt([1200 => 50, 2000 => 50]), 2100 - $earnedTotal);
                        } elseif ($needsGold) {
                            $bonus = max($this->weightedInt([200 => 50, 400 => 50]), 1250 - $earnedTotal);
                        } else {
                            $bonus = $this->weightedInt([200 => 35, 400 => 30, 800 => 25, 1200 => 10]);
                        }

                        $runningPoints += $bonus;
                        $transactionRows[] = $this->ledgerRow($customer, $customer->branch_id, 'loyalty_adjust', [
                            'points' => $bonus,
                            'running_points' => $runningPoints,
                            'running_deposit' => $runningDeposit,
                            'description' => 'Promo bonus '.$bonus.' pts',
                            'created_at' => $this->randomDateBetween($events[array_key_last($events)]['at'], now()),
                        ]);
                    }
                }

                $lifetimePoints = $runningPoints + $redeemed;
                $customer->lifetime_points = $lifetimePoints;
                $customer->loyalty_points = $runningPoints;
                $customer->loyalty_tier = app(CustomerAccountService::class)->tierFor($lifetimePoints, $lifetimeSpend);
                if ($depositBalance > 0) {
                    $customer->deposit_balance = $depositBalance;
                }
            }

            $customer->lifetime_spend = round((float) $customer->lifetime_spend + $lifetimeSpend, 2);
            $customer->save();
        }

        foreach (array_chunk($transactionRows, 500) as $chunk) {
            DB::table('customer_transactions')->insert($chunk);
        }
    }

    private function ledgerRow(Customer $customer, int $branchId, string $type, array $data): array
    {
        $createdAt = $data['created_at'] ?? now();
        unset($data['created_at']);

        return array_merge([
            'customer_id' => $customer->id,
            'branch_id' => $branchId,
            'type' => $type,
            'amount' => $data['amount'] ?? 0,
            'points' => $data['points'] ?? 0,
            'running_credit' => $data['running_credit'] ?? 0,
            'running_deposit' => $data['running_deposit'] ?? 0,
            'running_points' => $data['running_points'] ?? 0,
            'reference_type' => $data['reference_type'] ?? null,
            'reference_id' => $data['reference_id'] ?? null,
            'description' => $data['description'] ?? null,
            'created_by' => $this->sellers[array_rand($this->sellers)]->id,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ], $data);
    }

    private function seedLayaways(): void
    {
        foreach ([2024 => 20, 2025 => 20, 2026 => 20] as $year => $count) {
            for ($i = 0; $i < $count; $i++) {
                $createdAt = $this->randomSaleTimestamp($year);
                $customer = $this->customers === [] ? null : $this->customers[array_rand($this->customers)];
                if (! $customer) {
                    return;
                }

                $branch = $this->pickBranch();

                [$items, $subtotal] = $this->buildSaleItems($branch);
                $total = round($subtotal * random_int(85, 100) / 100, 2);
                $deposit = round($total * random_int(25, 50) / 100, 2);

                $this->saleCounter++;
                $saleNo = 'SALE-'.$createdAt->format('YmdHis').'-'.Str::padLeft((string) $this->saleCounter, 4, '0');
                $shiftId = $this->shifts[$branch->id][$createdAt->format('Y-n')] ?? null;

                $sale = Sale::create([
                    'branch_id' => $branch->id,
                    'user_id' => $this->sellersForBranch($branch)['id'],
                    'customer_id' => $customer['model']->id,
                    'shift_id' => $shiftId,
                    'sale_no' => $saleNo,
                    'receipt_no' => $saleNo,
                    'subtotal' => $total,
                    'discount_total' => 0,
                    'tax_total' => 0,
                    'total' => $total,
                    'amount_paid' => $deposit,
                    'change_amount' => 0,
                    'status' => 'active',
                    'payment_status' => 'partial',
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt,
                ]);

                $itemRows = [];
                foreach ($items as $item) {
                    $itemRows[] = [
                        'sale_id' => $sale->id,
                        'product_id' => $item['product_id'],
                        'product_variant_id' => null,
                        'quantity' => $item['quantity'],
                        'original_unit_price' => $item['original'],
                        'unit_price' => $item['price'],
                        'discount' => 0,
                        'line_total' => $item['line_total'],
                        'cost_price' => $item['cost'],
                        'profit' => round($item['line_total'] - $item['cost'] * $item['quantity'], 2),
                        'is_loss' => false,
                        'loss_reason' => null,
                        'price_overridden' => false,
                        'price_override_reason' => null,
                        'price_override_approved_by' => null,
                        'created_at' => $createdAt,
                        'updated_at' => $createdAt,
                    ];
                }
                DB::table('sale_items')->insert($itemRows);

                $paidSoFar = $deposit;
                $instalments = [];
                $future = $createdAt->copy()->addWeeks(random_int(1, 12));
                while ($paidSoFar < $total && $future <= now()) {
                    $instalment = round(min($total - $paidSoFar, $total * random_int(10, 40) / 100), 2);
                    if ($instalment <= 0) {
                        break;
                    }
                    $instalments[] = ['amount' => $instalment, 'at' => $future->setTime(random_int(9, 19), random_int(0, 59))];
                    $paidSoFar = round($paidSoFar + $instalment, 2);
                    $future = $future->copy()->addWeeks(random_int(1, 4));
                }

                $status = $paidSoFar >= $total ? 'fulfilled' : 'active';
                $layaway = Layaway::create([
                    'sale_id' => $sale->id,
                    'customer_id' => $customer['model']->id,
                    'branch_id' => $branch->id,
                    'total_amount' => $total,
                    'deposit_amount' => $deposit,
                    'paid_amount' => $paidSoFar,
                    'balance' => round($total - $paidSoFar, 2),
                    'status' => $status,
                    'due_date' => $createdAt->copy()->addMonths(3),
                    'created_by' => $this->sellersForBranch($branch)['id'],
                    'created_at' => $createdAt,
                    'updated_at' => $status === 'fulfilled' ? end($instalments)['at'] ?? $createdAt : now(),
                ]);

                $layawayPaymentRows = [['amount' => $deposit, 'at' => $createdAt]];
                foreach ($instalments as $instalment) {
                    $layawayPaymentRows[] = ['amount' => $instalment['amount'], 'at' => $instalment['at']];
                }
                $rows = [];
                foreach ($layawayPaymentRows as $row) {
                    $rows[] = [
                        'layaway_id' => $layaway->id,
                        'amount' => $row['amount'],
                        'method' => ['cash', 'mobile'][random_int(0, 1)],
                        'reference' => null,
                        'received_by' => $this->sellersForBranch($branch)['id'],
                        'created_at' => $row['at'],
                        'updated_at' => $row['at'],
                    ];
                }
                DB::table('layaway_payments')->insert($rows);

                $this->trackCustomerSale(['model' => $customer['model']], $sale, [], $createdAt);
            }
        }
    }

    private function seedParkedSales(): void
    {
        $open = 0;
        foreach ([2024 => 12, 2025 => 12, 2026 => 8] as $year => $count) {
            for ($i = 0; $i < $count; $i++) {
                $heldAt = $this->randomSaleTimestamp($year);
                $branch = $this->pickBranch();
                $recalledAt = $year < 2026 ? $heldAt->copy()->addMinutes(random_int(30, 150)) : null;
                $open += 1;

                $cart = [];
                $productCount = $this->weightedInt([1 => 50, 2 => 30, 3 => 20]);
                for ($p = 0; $p < $productCount; $p++) {
                    $product = $this->products[random_int(0, count($this->products) - 1)];
                    $cart[] = [
                        'product_id' => $product['id'],
                        'product_variant_id' => null,
                        'name' => 'Demo product',
                        'sku' => $product['sku'] ?? 'SKU',
                        'quantity' => $this->weightedInt([1 => 60, 2 => 30, 3 => 10]),
                        'price' => $product['price'],
                    ];
                }

                ParkedSale::create([
                    'branch_id' => $branch->id,
                    'user_id' => $this->sellersForBranch($branch)['id'],
                    'customer_id' => random_int(1, 100) <= 40 ? ($this->customers[array_rand($this->customers)]['model']->id ?? null) : null,
                    'parked_no' => 'PARK-'.$heldAt->format('YmdHis').'-'.Str::padLeft((string) $i, 3, '0'),
                    'cart_json' => $cart,
                    'reference' => random_int(1, 100) <= 25 ? 'Customer call-in' : null,
                    'held_at' => $heldAt,
                    'recalled_at' => $recalledAt,
                    'created_at' => $heldAt,
                    'updated_at' => $recalledAt ?? $heldAt,
                ]);
            }
        }
    }

    private function seedStockAlerts(): void
    {
        $flag = 0;
        foreach ($this->branches as $branch) {
            $stocks = Stock::where('branch_id', $branch->id)->get();

            foreach ($stocks as $stock) {
                if ($flag % 5 === 4) {
                    $target = 0;
                } elseif ($flag % 5 === 3) {
                    $product = Product::find($stock->product_id);
                    $target = max((int) ($product->reorder_level) - 2, 0);
                } else {
                    continue;
                }

                if (random_int(1, 100) <= 25 && $stock->quantity > $target) {
                    $stock->update(['quantity' => $target]);
                }
                $flag++;
            }
        }
    }

    private function seedActivity(): void
    {
        $events = [
            ['event' => 'sale', 'description' => 'Completed sale {no}'],
            ['event' => 'stock_restock', 'description' => 'Stock restocked for {p} product(s)'],
            ['event' => 'coupon', 'description' => 'Coupon {c} applied to checkout'],
            ['event' => 'loyalty', 'description' => 'Loyalty points adjusted for customer'],
            ['event' => 'shift_close', 'description' => 'Shift closed with expected drawer count'],
            ['event' => 'layaway', 'description' => 'Layaway instalment received'],
        ];

        $rows = [];
        for ($i = 0; $i < 120; $i++) {
            $createdAt = $this->randomDate(2024, 2026)->setTime(random_int(8, 21), random_int(0, 59));
            $branch = $this->pickBranch();
            $seller = $this->sellersForBranch($branch);
            $event = $events[array_rand($events)];

            $rows[] = [
                'log_name' => 'default',
                'description' => strtr($event['description'], [
                    '{no}' => 'SALE-'.$createdAt->format('Ymd-His').'-X',
                    '{p}' => random_int(1, 20),
                    '{c}' => $this->coupons[array_rand($this->coupons)]->code ?? 'DEMO',
                ]),
                'event' => $event['event'],
                'subject_type' => $event['event'] === 'sale' ? Sale::class : null,
                'subject_id' => $event['event'] === 'sale' ? (Sale::inRandomOrder()->value('id') ?? null) : null,
                'causer_type' => User::class,
                'causer_id' => $seller['id'],
                'properties' => json_encode([]),
                'branch_id' => $branch->id,
                'role' => 'Seller',
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ];
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('activity_log')->insert($chunk);
        }
    }

    private function pickBranch(?int $preferredId = null): Branch
    {
        if ($preferredId !== null && isset($this->branchById[$preferredId])) {
            return $this->branchById[$preferredId];
        }

        return $this->branches[$this->weightedPick(self::BRANCH_WEIGHTS)];
    }

    private function sellersForBranch(Branch $branch): User
    {
        $sellers = array_values(array_filter($this->sellers, fn ($user) => $user->branch_id === $branch->id));

        return $sellers[array_rand($sellers)];
    }

    private function randomSaleTimestamp(int $year): Carbon
    {
        $end = $year === now()->year ? now()->subDay() : Carbon::create($year, 12, 31);
        $days = random_int(0, (int) Carbon::create($year, 1, 1)->diffInDays($end));

        $date = Carbon::create($year, 1, 1)->addDays($days);
        if (in_array($date->dayOfWeek, [Carbon::SATURDAY, Carbon::SUNDAY], true) && random_int(1, 3) <= 1) {
            $date->addDays(random_int(1, 2));
        }

        $minutes = random_int(8 * 60, 20 * 60 + 59);

        return $date->setTime(intdiv($minutes, 60), $minutes % 60);
    }

    private function randomDate(int $fromYear, int $toYear): Carbon
    {
        $from = Carbon::create($fromYear, 1, 1);
        $to = $toYear >= now()->year ? now() : Carbon::create($toYear, 12, 31);

        return $from->copy()->addDays(random_int(0, max((int) $from->diffInDays($to), 1)));
    }

    private function randomDateBetween(CarbonInterface $from, CarbonInterface $to): CarbonInterface
    {
        $span = max((int) $from->diffInDays($to), 1);

        return $from->copy()->addDays(random_int(0, $span));
    }

    private function weightedInt(array $pairs): int
    {
        $total = array_sum($pairs);
        $roll = random_int(1, $total);
        foreach ($pairs as $value => $weight) {
            if ($roll <= $weight) {
                return (int) $value;
            }
            $roll -= $weight;
        }

        return (int) array_key_last($pairs);
    }

    private function weightedIndex(int $max): int
    {
        return random_int(0, $max);
    }

    private function weightedPick(array $weighted): string
    {
        $total = array_sum($weighted);
        $roll = random_int(1, $total);
        foreach ($weighted as $label => $weight) {
            if ($roll <= $weight) {
                return (string) $label;
            }
            $roll -= $weight;
        }

        return (string) array_key_last($weighted);
    }
}
