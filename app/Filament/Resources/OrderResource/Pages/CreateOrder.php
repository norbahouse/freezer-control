<?php

declare(strict_types=1);

namespace App\Filament\Resources\OrderResource\Pages;

use Filament\Forms\Form;
use Illuminate\Support\HtmlString;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Wizard;
use Filament\Support\Exceptions\Halt;
use Illuminate\Support\Facades\Blade;
use Filament\Forms\Components\Section;
use Illuminate\Database\Eloquent\Model;
use Filament\Forms\Components\TextInput;
use App\Filament\Resources\OrderResource;
use Filament\Forms\Components\Wizard\Step;
use Filament\Resources\Pages\CreateRecord;
use Filament\Forms\Components\Actions\Action;
use Filament\Resources\Pages\Concerns\HasWizard;

class CreateOrder extends CreateRecord
{
    use HasWizard;

    protected static string $resource = OrderResource::class;


    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data = [
            'customer_id' => $this->data['customer_id'],
            'items' => $this->data['items'],
            'total' => $this->data['total'],

        ];
        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }



    public function form(Form $form): Form
    {
        return parent::form($form)
            ->schema([
                Wizard::make($this->getSteps())
                    ->startOnStep($this->getStartStep())
                    ->startOnStep(3) // todo: remover após implementação do checkout
                    ->skippable($this->hasSkippableSteps())
                    ->contained(false)
                    ->cancelAction($this->getCancelFormAction())
                    ->submitAction(
                        Action::make('create')
                            ->label('Finalizar pedido')
                            ->submit('create')
                            ->keyBindings(['mod+s'])
                    )

            ])
            ->columns(null);
    }

    protected function getSteps(): array
    {
        return [
            Step::make('Cliente')
                ->icon('heroicon-m-user')
                ->description('Selecione o cliente')
                ->schema([
                    Section::make()
                        ->schema(
                            OrderResource::getCustomerFormDetails()
                        ),
                ]),
            Step::make('Itens do pedido')
                ->icon('heroicon-m-shopping-bag')
                ->description('Adicioine os itens ao pedido')
                ->schema([
                    Grid::make(2)
                        ->schema([
                            TextInput::make('customer_name')
                                ->label('Cliente')
                                ->disabled()
                                ->columnSpan(1),
                            TextInput::make('placeholder_total')
                                ->label('Valor total da compra')
                                ->disabled()
                                ->prefix('R$')
                                ->placeholder(function ($get, $set) {
                                    $fields = $get('items');
                                    $sum = 0.0;
                                    foreach ($fields as $field) {
                                        $sum += floatval($field['sub_total']);
                                    }
                                    $sum = number_format($sum, 2, '.', '');
                                    $set('total', $sum);
                                    return $sum;
                                })
                                ->columnSpan(1),
                            Hidden::make('total')
                                ->default(0.0),
                            Section::make()
                                ->schema([
                                    OrderResource::getItemsRepeater()
                                ]),
                        ]),
                ]),

            Step::make('Pagamento')
                ->icon('heroicon-s-credit-card')
                ->description('Selecione a forma de pagamento')
                ->schema([
                    Grid::make(2)
                        ->schema([
                            TextInput::make('customer_name')
                                ->label('Cliente')
                                ->disabled()
                                ->columnSpan(1),
                            TextInput::make('total')
                                ->label('Valor total da compra')
                                ->disabled()
                                ->prefix('R$')
                                ->placeholder(function ($get) {
                                    $fields = $get('items');
                                    $sum = 0.0;
                                    foreach ($fields as $field) {

                                        $sum += floatval($field['sub_total']);
                                    }
                                    return number_format($sum, 2, '.', '');
                                })
                                ->columnSpan(1),
                        ]),

                    Section::make()
                        ->schema(OrderResource::getPaymentFormDetails()),
                ]),
        ];
    }

    public function payWithPix()
    {

        $data = [
            "billingType" => "PIX", // "CREDIT_CARD", "PIX", "BOLETO"
            "customer" => "cus_000005891625",
            "dueDate" => now()->format('Y-m-d'),
            "value" => 100,
            "description" => "Pedido 056984",
            "daysAfterDueDateToCancellationRegistration" => 1,
            "externalReference" => "056984",
            "postalService" => false,
        ];


        // 2. Realiza a cobrança
        $payment = $gateway->payment()->create($data);
    }


    protected function beforeCreate(): void
    {
        throw new Halt();
        dd($this->data);

        // 1. Prepara os dados necessários para a cobrança
        $data = [
            "billingType" => "PIX", // "CREDIT_CARD", "PIX", "BOLETO"
            "discount" => [
                "value" => 10,
                "dueDateLimitDays" => 0
            ],
            "interest" => [
                "value" => 2
            ],
            "fine" => [
                "value" => 1
            ],
            "customer" => "cus_000005891625",
            "dueDate" => now()->format('Y-m-d'),
            "value" => 100,
            "description" => "Pedido 056984",
            "daysAfterDueDateToCancellationRegistration" => 1,
            "externalReference" => "056984",
            "postalService" => false,
        ];


        // 2. Realiza a cobrança
        $payment = $gateway->payment()->create($data);

        // 3.1 Se sucesso, armazena as informações necessárias em 'order_transactions'

        // 3.2 Se erro, exibir notoficação em tela.
    }
}
