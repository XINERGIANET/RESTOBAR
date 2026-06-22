<?php

use App\Http\Controllers\AreaController;
use App\Http\Controllers\DigitalWalletController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\BankController;
use App\Http\Controllers\BranchController;
use App\Http\Controllers\CardController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\ModulesController;
use App\Http\Controllers\PersonController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\SalesController;
use App\Http\Controllers\MovementTypeController;
use App\Http\Controllers\DocumentTypeController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\OperationsController;
use App\Http\Controllers\ParameterCategoriesController;
use App\Http\Controllers\ParameterController;
use App\Http\Controllers\MenuOptionController;
use App\Http\Controllers\PaymentConceptController;
use App\Http\Controllers\PaymentGatewaysController;
use App\Http\Controllers\PaymentMethodController;
use App\Http\Controllers\ProductBranchController;
use App\Http\Controllers\TableController;
use App\Http\Controllers\UserProfileController;
use App\Http\Controllers\ViewsController;
use App\Http\Controllers\ShiftController;
use App\Http\Controllers\TaxRateController;
use App\Http\Controllers\PettyCashController;
use App\Http\Controllers\BoxController;
use App\Http\Controllers\KardexController;
use App\Http\Controllers\RecipeBookController;
use App\Http\Controllers\UnitController;
use App\Http\Controllers\WarehouseMovementController;
use App\Http\Controllers\ShiftCashController;
use App\Http\Controllers\AccountsReceivableController;
use App\Http\Controllers\CashRegisterController;
use App\Http\Controllers\PurchaseController;
use App\Http\Controllers\BranchParameterController;
use App\Http\Controllers\ProductTypeController;
use App\Http\Controllers\PrintBridgeController;
use App\Http\Controllers\PrinterBranchController;
use App\Http\Controllers\QzTrayController;

Route::prefix('restaurante')->name('restaurant.')->group(function () {
    Route::view('/', 'restaurant.home', ['title' => 'Xinergia Restaurante'])->name('home');
    Route::view('/menu', 'restaurant.menu', ['title' => 'Menu'])->name('menu');
    Route::view('/reservas', 'restaurant.reservations', ['title' => 'Reservas'])->name('reservations');
    Route::view('/historia', 'restaurant.about', ['title' => 'Historia'])->name('about');
    Route::view('/eventos', 'restaurant.events', ['title' => 'Eventos'])->name('events');
    Route::view('/galeria', 'restaurant.gallery', ['title' => 'Galeria'])->name('gallery');
    Route::view('/contacto', 'restaurant.contact', ['title' => 'Contacto'])->name('contact');
    Route::view('/sucursales', 'restaurant.locations', ['title' => 'Sucursales'])->name('locations');
});


Route::get('/signin', [AuthenticatedSessionController::class, 'create'])
    ->middleware('guest')
    ->name('login');

Route::post('/signin', [AuthenticatedSessionController::class, 'store'])
    ->middleware('guest')
    ->name('login.store');

Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

Route::view('/signup', 'pages.auth.signup', ['title' => 'Sign Up'])
    ->middleware('guest')
    ->name('signup');

Route::middleware('auth')->group(function () {
    Route::resource('/admin/herramientas/empresas', CompanyController::class)
        ->names('admin.companies')
        ->parameters(['empresas' => 'company']);
    Route::resource('/admin/herramientas/empresas.sucursales', BranchController::class)
        ->names('admin.companies.branches')
        ->parameters(['empresas' => 'company', 'sucursales' => 'branch']);
    Route::resource('/admin/herramientas/empresas.sucursales.personal', PersonController::class)
        ->names('admin.companies.branches.people')
        ->parameters(['empresas' => 'company', 'sucursales' => 'branch', 'personal' => 'person'])
        ->only(['index', 'store', 'edit', 'update', 'destroy']);
    Route::patch('/admin/herramientas/empresas/{company}/sucursales/{branch}/personal/{person}/usuario/password', [PersonController::class, 'updatePassword'])
        ->name('admin.companies.branches.people.user.password');
    Route::get('/admin/herramientas/empresas/{company}/sucursales/{branch}/perfiles', [BranchController::class, 'profiles'])
        ->name('admin.companies.branches.profiles.index');
    Route::post('/admin/herramientas/empresas/{company}/sucursales/{branch}/perfiles/asignar', [BranchController::class, 'assignBranchProfiles'])
        ->name('admin.companies.branches.profiles.assign');
    Route::get('/admin/herramientas/empresas/{company}/sucursales/{branch}/perfiles/{profile}/operaciones', [BranchController::class, 'profileOperationsIndex'])
        ->name('admin.companies.branches.profiles.operations.index');
    Route::post('/admin/herramientas/empresas/{company}/sucursales/{branch}/perfiles/{profile}/operaciones/asignar', [BranchController::class, 'assignProfileOperations'])
        ->name('admin.companies.branches.profiles.operations.assign');
    Route::patch('/admin/herramientas/empresas/{company}/sucursales/{branch}/perfiles/{profile}/operaciones/{operation}/toggle', [BranchController::class, 'toggleProfileOperation'])
        ->name('admin.companies.branches.profiles.operations.toggle');
    Route::get('/admin/herramientas/empresas/{company}/sucursales/{branch}/vistas', [BranchController::class, 'viewsIndex'])
        ->name('admin.companies.branches.views.index');
    Route::post('/admin/herramientas/empresas/{company}/sucursales/{branch}/vistas', [BranchController::class, 'updateViews'])
        ->name('admin.companies.branches.views.update');
    Route::delete('/admin/herramientas/empresas/{company}/sucursales/{branch}/vistas/{view}', [BranchController::class, 'removeViewAssignment'])
        ->name('admin.companies.branches.views.destroy');
    Route::get('/admin/herramientas/empresas/{company}/sucursales/{branch}/vistas/{view}/operaciones', [BranchController::class, 'viewOperationsIndex'])
        ->name('admin.companies.branches.views.operations.index');
    Route::post('/admin/herramientas/empresas/{company}/sucursales/{branch}/vistas/{view}/operaciones/asignar', [BranchController::class, 'assignViewOperations'])
        ->name('admin.companies.branches.views.operations.assign');
    Route::patch('/admin/herramientas/empresas/{company}/sucursales/{branch}/vistas/{view}/operaciones/{branchOperation}/toggle', [BranchController::class, 'toggleViewOperation'])
        ->name('admin.companies.branches.views.operations.toggle');
    Route::get('/admin/herramientas/empresas/{company}/sucursales/{branch}/perfiles/{profile}/permisos', [BranchController::class, 'profilePermissions'])
        ->name('admin.companies.branches.profiles.permissions.index');
    Route::post('/admin/herramientas/empresas/{company}/sucursales/{branch}/perfiles/{profile}/permisos/asignar', [BranchController::class, 'assignProfilePermissions'])
        ->name('admin.companies.branches.profiles.permissions.assign');
    Route::patch('/admin/herramientas/empresas/{company}/sucursales/{branch}/perfiles/{profile}/permisos/{permission}', [BranchController::class, 'toggleProfilePermission'])
        ->name('admin.companies.branches.profiles.permissions.toggle');
    Route::resource('/admin/herramientas/perfiles', ProfileController::class)
        ->names('admin.profiles')
        ->parameters(['perfiles' => 'profile'])
        ->only(['index', 'store', 'edit', 'update', 'destroy']);
    Route::resource('/admin/herramientas/roles', RoleController::class)
        ->names('admin.roles')
        ->parameters(['roles' => 'role'])
        ->only(['index', 'store', 'edit', 'update', 'destroy']);
    Route::get('/ventas/create', [OrderController::class, 'createCounterSalePos'])
        ->name('sales.create');
    Route::resource('/ventas', SalesController::class)
        ->names('sales')
        ->parameters(['ventas' => 'sale'])
        ->only(['index', 'store', 'edit', 'update', 'destroy']);
    Route::get('/admin/ventas/pdf', [SalesController::class, 'exportPdf'])->name('admin.sales.pdf');
    Route::get('/admin/ventas/excel', [SalesController::class, 'exportExcel'])->name('admin.sales.excel');
    Route::get('/admin/ventas/pdf/{sale}', [SalesController::class, 'printPdf'])->name('admin.sales.print.pdf');
    Route::get('/admin/ventas/ticket/{sale}', [SalesController::class, 'printTicket'])->name('admin.sales.print.ticket');
    Route::get('/admin/ventas/consultar-documento', [SalesController::class, 'lookupFiscalDocument'])->name('sales.lookup.document');
    Route::get('/admin/ventas/{sale}/electronico/pdf-a4', [SalesController::class, 'redirectElectronicPdfA4'])->name('sales.electronic.pdf.a4');
    Route::get('/admin/ventas/{sale}/electronico/xml', [SalesController::class, 'redirectElectronicXml'])->name('sales.electronic.xml');
    Route::get('/admin/ventas/{sale}/electronico/cdr', [SalesController::class, 'redirectElectronicCdr'])->name('sales.electronic.cdr');
    Route::post('/admin/ventas/{sale}/convertir-electronico', [SalesController::class, 'convertTicketToElectronic'])->name('sales.convert.electronic');
    Route::post('/admin/ventas/ticket-termica', [SalesController::class, 'printTicketThermalNetwork'])
        ->name('sales.print.ticket.thermal');

    // POS: vista de cobro (antes era modal)
    Route::get('/ventas/cobrar', [SalesController::class, 'charge'])
        ->name('sales.charge');

    // API: obtener caja de sesión
    Route::get('/api/session/cash-register', [SalesController::class, 'getSessionCashRegister'])
        ->name('api.session.cash-register');

    // POS: procesar venta (usado por resources/views/sales/create.blade.php)
    Route::post('/admin/ventas/procesar', [SalesController::class, 'processSale'])
        ->name('sales.process')
        ->middleware('active.shift');

    // POS: guardar venta como borrador/pendiente
    Route::post('/admin/ventas/borrador', [SalesController::class, 'saveDraft'])
        ->name('sales.draft')
        ->middleware('active.shift');

    Route::resource('/products/tipos-movimiento', MovementTypeController::class)
        ->names('admin.movement-types')
        ->parameters(['tipos-movimiento' => 'movementType'])
        ->only(['index', 'store', 'edit', 'update', 'destroy']);
    Route::resource('/products/tipos-documento', DocumentTypeController::class)
        ->names('admin.document-types')
        ->parameters(['tipos-documento' => 'documentType'])
        ->only(['index', 'store', 'edit', 'update', 'destroy']);
    Route::resource('/products/categorias', CategoryController::class)
        ->names('categories')
        ->parameters(['categorias' => 'category'])
        ->only(['index', 'store', 'edit', 'update', 'destroy']);
    Route::resource('/products/productos', ProductController::class)
        ->names('products')
        ->parameters(['productos' => 'product'])
        ->only(['index', 'store', 'edit', 'update', 'destroy']);

    Route::get('/products/importar', [ProductController::class, 'importForm'])
        ->name('products.import.form');
    Route::post('/products/importar', [ProductController::class, 'import'])
        ->name('products.import');
    Route::get('/products/importar/plantilla', [ProductController::class, 'downloadTemplate'])
        ->name('products.import.template');

    Route::resource('/tipos-producto', ProductTypeController::class)
        ->names('product_types')
        ->parameters(['tipos-producto' => 'productType'])
        ->only(['index', 'store', 'edit', 'update', 'destroy']);

    Route::get('/products/productos/{product}/product-branches/create', [ProductBranchController::class, 'create'])
        ->name('products.product_branches.create');
    Route::post('/products/productos/{product}/product-branches', [ProductBranchController::class, 'store'])
        ->name('products.product_branches.store');
    Route::post('/products/product-branches/store', [ProductBranchController::class, 'storeGeneric'])
        ->name('product_branches.store_generic');
    Route::put('/products/product-branches/{productBranch}', [ProductBranchController::class, 'update'])
        ->name('product_branches.update');

    //Kardex
    Route::get('/herramientas/kardex', [KardexController::class, 'index'])
        ->name('kardex.index');
    Route::get('/herramientas/kardex/pdf', [KardexController::class, 'pdf'])
        ->name('kardex.exportPdf');
    //Rutas de ordenes
    Route::resource('/Pedidos', OrderController::class)
        ->names('orders')
        ->parameters(['orders' => 'order'])
        ->only(['index', 'create']); // Solo incluir los métodos que existen

    Route::get('/Pedidos/mesas-data', [OrderController::class, 'tablesData'])
        ->name('orders.tablesData');

    Route::get('/Pedidos/reporte', [OrderController::class, 'list'])
        ->name('orders.list');
    Route::get('/Pedidos/cobrar', [OrderController::class, 'charge'])
        ->name('orders.charge');
    Route::get('/Pedidos/reporte/pdf', [OrderController::class, 'pdfReport'])
        ->name('orders.pdf');
    Route::post('/Pedidos/procesar', [OrderController::class, 'processOrder'])
        ->name('orders.process')
        ->middleware('active.shift');
    Route::post('/Pedidos/imprimir-comanda', [OrderController::class, 'printKitchenTicketThermal'])
        ->name('orders.print.kitchen.thermal')
        ->middleware('active.shift');
    Route::post('/Pedidos/imprimir-comanda/pdf', [OrderController::class, 'printKitchenTicketPdf'])
        ->name('orders.print.kitchen.pdf')
        ->middleware('active.shift');
    Route::post('/Pedidos/imprimir-comanda/pdf-link', [OrderController::class, 'createKitchenTicketPdfLink'])
        ->name('orders.print.kitchen.pdf.link')
        ->middleware('active.shift');
    Route::post('/Pedidos/imprimir-precuenta', [OrderController::class, 'printPreAccountThermal'])
        ->name('orders.print.preaccount.thermal')
        ->middleware('active.shift');
    Route::post('/Pedidos/imprimir-precuenta/pdf', [OrderController::class, 'printPreAccountPdf'])
        ->name('orders.print.preaccount.pdf')
        ->middleware('active.shift');
    Route::post('/Pedidos/imprimir-precuenta/pdf-link', [OrderController::class, 'createPreAccountPdfLink'])
        ->name('orders.print.preaccount.pdf.link')
        ->middleware('active.shift');
    Route::get('/Pedidos/imprimir-ticket/pdf/{token}', [OrderController::class, 'showStoredTextTicketPdf'])
        ->name('orders.print.ticket.pdf.show')
        ->middleware('active.shift');

    Route::post('/Pedidos/procesar-pago', [OrderController::class, 'processOrderPayment'])
        ->name('orders.processOrderPayment')
        ->middleware('active.shift');

    Route::post('/Pedidos/cancelar', [OrderController::class, 'cancelOrder'])
        ->name('orders.cancelOrder');

    Route::post('/Pedidos/abrir-mesa', [OrderController::class, 'openTable'])
        ->name('orders.openTable');

    Route::post('/Pedidos/mover-mesa', [OrderController::class, 'moveTable'])
        ->name('orders.moveTable');

    // PIN de mozo (por sucursal)
    Route::post('/Pedidos/validar-pin-mozo', [OrderController::class, 'validateWaiterPin'])
        ->name('orders.validateWaiterPin');

    // Dashboard pages
    Route::get('/', [\App\Http\Controllers\DashboardController::class, 'index'])->name('dashboard');
    Route::get('/dashboard/productos-vendidos/pdf', [\App\Http\Controllers\DashboardController::class, 'productsSoldPdf'])
        ->name('dashboard.productsSoldPdf');

    // calender pages
    Route::get('/calendar', function () {
        return view('pages.calender', ['title' => 'Calendar']);
    })->name('calendar');

    // perfil del usuario autenticado
    Route::get('/profile', [UserProfileController::class, 'show'])->name('profile');
    Route::put('/profile', [UserProfileController::class, 'update'])->name('profile.update');
    Route::put('/profile/password', [UserProfileController::class, 'updatePassword'])->name('profile.password');

    // form pages
    Route::get('/form-elements', function () {
        return view('pages.form.form-elements', ['title' => 'Form Elements']);
    })->name('form-elements');

    // tables pages
    Route::get('/basic-tables', function () {
        return view('pages.tables.basic-tables', ['title' => 'Basic Tables']);
    })->name('basic-tables');

    // pages
    Route::get('/blank', function () {
        return view('pages.blank', ['title' => 'Blank']);
    })->name('blank');

    // error pages
    Route::get('/error-404', function () {
        return view('pages.errors.error-404', ['title' => 'Error 404']);
    })->name('error-404');

    // chart pages
    Route::get('/line-chart', function () {
        return view('pages.chart.line-chart', ['title' => 'Line Chart']);
    })->name('line-chart');

    Route::get('/bar-chart', function () {
        return view('pages.chart.bar-chart', ['title' => 'Bar Chart']);
    })->name('bar-chart');

    // ui elements pages
    Route::get('/alerts', function () {
        return view('pages.ui-elements.alerts', ['title' => 'Alerts']);
    })->name('alerts');

    Route::get('/avatars', function () {
        return view('pages.ui-elements.avatars', ['title' => 'Avatars']);
    })->name('avatars');

    Route::get('/badge', function () {
        return view('pages.ui-elements.badges', ['title' => 'Badges']);
    })->name('badges');

    Route::get('/buttons', function () {
        return view('pages.ui-elements.buttons', ['title' => 'Buttons']);
    })->name('buttons');

    Route::get('/image', function () {
        return view('pages.ui-elements.images', ['title' => 'Images']);
    })->name('images');

    Route::get('/videos', function () {
        return view('pages.ui-elements.videos', ['title' => 'Videos']);
    })->name('videos');

    // Modulos administrativos
    Route::view('/admin/herramientas/usuarios', 'pages.blank', ['title' => 'Usuarios']);
    Route::view('/admin/herramientas/sucursales', 'pages.blank', ['title' => 'Sucursales']);

    // Modulos
    Route::resource('admin/herramientas/modulos', ModulesController::class)
        ->names('admin.modules')
        ->parameters(['modulos' => 'module']);
    Route::delete('/admin/herramientas/modulos/{module}', [ModulesController::class, 'destroy'])->name('admin.modules.destroy');
    Route::resource('/admin/herramientas/modulos.menu', MenuOptionController::class)
        ->names('admin.modules.menu_options')
        ->parameters(['modulos' => 'module', 'menu' => 'menuOption']);

    // Vistas
    Route::resource('admin/herramientas/vistas', ViewsController::class)
        ->names('admin.views')
        ->parameters(['vistas' => 'view']);
    Route::delete('/admin/herramientas/vistas/{view}', action: [ViewsController::class, 'destroy'])->name('admin.views.destroy');

    //Bancos
    Route::resource('/admin/herramientas/bancos', BankController::class)
        ->names('admin.banks')
        ->parameters(['bancos' => 'bank']);
    //Operaciones de vistas
    Route::resource('admin/herramientas/vistas.operations', OperationsController::class)
        ->names('admin.views.operations')
        ->parameters(['vistas' => 'view', 'operations' => 'operation']);

    //Conceptos de pago
    Route::resource('admin/herramientas/conceptos-pago', PaymentConceptController::class)
        ->names('admin.payment_concepts')
        ->parameters(['conceptos-pago' => 'paymentConcept']);

    //Categorias de parametros
    Route::get('/admin/herramientas/parametros/categorias', [ParameterCategoriesController::class, 'index'])->name('admin.parameters.categories.index');
    Route::post('/admin/herramientas/parametros/categorias', [ParameterCategoriesController::class, 'store'])->name('admin.parameters.categories.store');
    Route::delete('/admin/herramientas/parametros/categorias/{parameterCategory}', [ParameterCategoriesController::class, 'destroy'])->name('admin.parameters.categories.destroy');
    Route::put('/admin/herramientas/parametros/categorias/{parameterCategory}', [ParameterCategoriesController::class, 'update'])->name('admin.parameters.categories.update');

    //Parametros
    Route::get('/admin/herramientas/parametros', [ParameterController::class, 'index'])->name('admin.parameters.index');
    Route::post('/admin/herramientas/parametros', [ParameterController::class, 'store'])->name('admin.parameters.store');
    Route::put('/admin/herramientas/parametros/{parameter}', [ParameterController::class, 'update'])->name('admin.parameters.update');
    Route::delete('/admin/herramientas/parametros/{parameter}', [ParameterController::class, 'destroy'])->name('admin.parameters.destroy');

    // Operaciones
    Route::get('/admin/herramientas/operaciones', [OperationsController::class, 'index'])->name('admin.operations.index');
    Route::post('/admin/herramientas/operaciones', [OperationsController::class, 'store'])->name('admin.operations.store');

    //Unidades
    Route::resource('/admin/herramientas/unidades', UnitController::class)
        ->names('admin.units')
        ->parameters(['unidades' => 'unit']);

    // Areas
    Route::resource('/areas', AreaController::class)
        ->names('areas')
        ->parameters(['areas' => 'area'])
        ->only(['index', 'store', 'edit', 'update', 'destroy']);

    Route::get('/areas/importar', [AreaController::class, 'importForm'])
        ->name('areas.import.form');
    Route::post('/areas/importar', [AreaController::class, 'import'])
        ->name('areas.import');
    Route::get('/areas/importar/plantilla', [AreaController::class, 'downloadTemplate'])
        ->name('areas.import.template');

    // Mesas
    Route::resource('/areas/{area}/mesas', TableController::class)
        ->names('areas.tables')
        ->parameters(['areas' => 'area']);
    Route::get('/mesas', [TableController::class, 'indexAll'])->name('tables.index');
    Route::post('/mesas', [TableController::class, 'storeGeneral'])->name('tables.store');
    Route::get('/mesas/{table}/edit', [TableController::class, 'editGeneral'])->name('tables.edit');
    Route::put('/mesas/{table}', [TableController::class, 'updateGeneral'])->name('tables.update');
    Route::delete('/mesas/{table}', [TableController::class, 'destroyGeneral'])->name('tables.destroy');

    //Tarjetas
    Route::resource('/admin/herramientas/tarjetas', CardController::class)
        ->names('admin.cards')
        ->parameters(['tarjetas' => 'card']);

    //Billeteras digitales
    Route::resource('/admin/herramientas/billeteras-digitales', DigitalWalletController::class)
        ->names('admin.digital_wallets')
        ->parameters(['billeteras-digitales' => 'digitalWallet']);

    //Pasarelas de pago
    Route::resource('/admin/herramientas/pasarela-pagos', PaymentGatewaysController::class)
        ->names('admin.payment_gateways')
        ->parameters(['pasarela-pagos' => 'paymentGateway']);

    //Métodos de pago
    Route::resource('/admin/herramientas/metodos-pago', PaymentMethodController::class)
        ->names('admin.payment_methods')
        ->parameters(['metodos-pago' => 'paymentMethod']);

    //Turnos
    Route::resource('/configuracion/turnos', ShiftController::class)
        ->names('shifts')
        ->parameters(['turnos' => 'shifts']);

    //Caja chica
    Route::get('/caja/caja-chica', [PettyCashController::class, 'redirectBase'])
        ->name('petty-cash.base');
    Route::group(['prefix' => 'caja/caja-chica/{cash_register_id}', 'as' => 'petty-cash.'], function () {
        Route::get('/', [PettyCashController::class, 'index'])->name('index');
        Route::get('/cierre', [PettyCashController::class, 'cierre'])->name('cierre');
        Route::get('/{movement}', [PettyCashController::class, 'show'])->name('show');
        Route::post('/', [PettyCashController::class, 'store'])->name('store');
        Route::get('/{movement}/edit', [PettyCashController::class, 'edit'])->name('edit');
        Route::put('/{movement}', [PettyCashController::class, 'update'])->name('update');
        Route::delete('/{movement}', [PettyCashController::class, 'destroy'])->name('destroy');
    });

    //Cajas
    Route::resource('/caja/cajas', BoxController::class)
        ->names('boxes')
        ->parameters(['cajas' => 'box']);

    // Turno por caja 
    Route::get('/shift-cash/redirect', [ShiftCashController::class, 'redirectBase'])
        ->name('shift-cash.redirect');
    Route::get('/caja/turno-caja/lista/{cash_register_id?}', [ShiftCashController::class, 'index'])
        ->name('shift-cash.index');
    Route::resource('/caja/turno-caja', ShiftCashController::class)
        ->names('shift-cash')
        ->parameters(['turno-caja' => 'shiftCash'])
        ->except(['index']);

    Route::get('/caja/turno-caja/{shiftCash}/print', [ShiftCashController::class, 'print'])
        ->name('shift-cash.print');
    Route::get('/caja/turno-caja/{shiftCash}/ventas-productos', [ShiftCashController::class, 'productsSold'])
        ->name('shift-cash.products-sold');

    Route::get('/caja/cuentas-por-cobrar', [AccountsReceivableController::class, 'index'])
        ->name('accounts-receivable.index');
    Route::get('/caja/cuentas-por-cobrar/{account_receivable_payable}/cobrar', [AccountsReceivableController::class, 'collect'])
        ->name('accounts-receivable.collect');
    Route::post('/caja/cuentas-por-cobrar/{account_receivable_payable}/cobrar', [AccountsReceivableController::class, 'storeCollection'])
        ->name('accounts-receivable.collect.store');

    //tasa de impuesto
    Route::resource('/admin/herramientas/tasas-impuesto', TaxRateController::class)
        ->names('admin.tax_rates')
        ->parameters(['tasas-impuesto' => 'taxRate']);

    // Movimientos de almacén: resource con mismo prefijo (movimientos_almacen) para que edit/update/destroy funcionen
    Route::get('/admin/herramientas/movimientos_almacen/entrada', [WarehouseMovementController::class, 'input'])
        ->name('warehouse_movements.input');
    Route::get('/admin/herramientas/movimientos_almacen/salida', [WarehouseMovementController::class, 'output'])
        ->name('warehouse_movements.output');
    Route::post('/admin/herramientas/movimientos_almacen/salida', [WarehouseMovementController::class, 'outputStore'])
        ->name('warehouse_movements.output.store');

    Route::resource('/admin/herramientas/movimientos_almacen', WarehouseMovementController::class)
        ->names('warehouse_movements')
        ->parameters(['movimientos_almacen' => 'warehouseMovement'])
        ->only(['index', 'store', 'show', 'edit', 'update', 'destroy']);

    // Reportes
    Route::get('/reportes/consolidado-productos', [\App\Http\Controllers\ReportController::class, 'consolidatedProducts'])
        ->name('reports.consolidated_products');
    Route::get('/reportes/consolidado-productos/excel', [\App\Http\Controllers\ReportController::class, 'consolidateProductsExcel'])
        ->name('reports.consolidated_products.excel');
    Route::get('/reportes/consolidado-productos/pdf', [\App\Http\Controllers\ReportController::class, 'consolidatedProductsPdf'])
        ->name('reports.consolidated_products.pdf');
    Route::get('/reportes/metodo-pago', [\App\Http\Controllers\ReportController::class, 'paymentMethod'])
        ->name('reports.payment_method');
    Route::get('/reportes/ventas-cliente', [\App\Http\Controllers\ReportController::class, 'salesByCustomer'])
        ->name('reports.sales_by_customer');
    Route::get('/reportes/ventas-finanzas', [\App\Http\Controllers\ReportController::class, 'salesAndFinances'])
        ->name('reports.sales_and_finances');
    Route::get('/reportes/ventas-finanzas/excel', [\App\Http\Controllers\ReportController::class, 'salesAndFinancesExcel'])
        ->name('reports.sales_and_finances.excel');
    Route::get('/reportes/ventas-finanzas/pdf', [\App\Http\Controllers\ReportController::class, 'salesAndFinancesPdf'])
        ->name('reports.sales_and_finances.pdf');

    //Recetario
    Route::resource('/cocina/recetario', RecipeBookController::class)
        ->names('recipe-book')
        ->parameters(['recetario' => 'recipe']);

    // SELECCIÓN DE SUCURSAL EN SESIÓN (categorías, productos, etc. se filtran por esta sucursal)
    Route::post('/sucursal/fijar', [BranchController::class, 'setSessionBranch'])->name('sucursal.fijar');

    // SELECCIÓN DE CAJA EN CABECERA POR SESSION
    Route::post('/caja/fijar', [CashRegisterController::class, 'set'])->name('caja.fijar');
    Route::get('/caja/seleccionar', [CashRegisterController::class, 'select'])->name('caja.seleccionar');

    //Compras
    Route::get('/compras/productos/{branchId}', [PurchaseController::class, 'getProductsByBranch'])->name('purchase.get-products-by-branch');
    Route::post('/compras/proveedor', [PurchaseController::class, 'storeProveedor'])->name('purchase.store-proveedor');
    Route::resource('compras', PurchaseController::class)
        ->names('purchase')
        ->parameters(['compras' => 'purchaseMovement']);

    // Configuración de parámetros
    Route::get('/configuracion/parametros-sucursal', [BranchParameterController::class, 'index'])
        ->name('branch-parameter.index');
    Route::post('/configuracion/parametros-sucursal/guardar', [BranchParameterController::class, 'store'])
        ->name('branch-parameter.store');

    //Configuracion de impresoras de sucursal
    Route::resource('/configuracion/impresoras-sucursal', PrinterBranchController::class)
        ->names('printers_branch')
        ->parameters(['impresoras-sucursal' => 'printerBranch'])
        ->only(['index', 'create', 'store', 'edit', 'update', 'destroy']);

    Route::get('/configuracion/personal', [PersonController::class, 'indexBranch'])
        ->name('configuracion.personal.index');

    Route::prefix('qz-tray')->name('qz.')->group(function () {
        Route::get('/certificate', [QzTrayController::class, 'certificate'])->name('certificate');
        Route::match(['GET', 'POST'], '/sign', [QzTrayController::class, 'sign'])->name('sign');
        Route::get('/prueba', [QzTrayController::class, 'test'])->name('test');
    });

    // Puente: celular → servidor (cola) → PC con QZ + BARRA2 USB
    Route::get('/print-bridge/worker', [PrintBridgeController::class, 'worker'])->name('print-bridge.worker');
    Route::get('/print-bridge/pull', [PrintBridgeController::class, 'pull'])
        ->middleware('throttle:120,1')
        ->name('print-bridge.pull');
    Route::post('/print-bridge/ack', [PrintBridgeController::class, 'ack'])
        ->middleware('throttle:120,1')
        ->name('print-bridge.ack');
});
