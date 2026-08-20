<div>
    <h1 class="mb-6 text-lg font-extrabold text-slate-900 dark:text-white">{{ __('painel.entrar') }}</h1>

    @if ($errors->any())
        <x-ui.alert tone="danger" class="mb-4">
            @foreach ($errors->all() as $error)
                <p>{{ $error }}</p>
            @endforeach
        </x-ui.alert>
    @endif

    <form method="POST" action="{{ route('login.store') }}" class="space-y-4">
        @csrf

        <x-ui.input label="{{ __('painel.email') }}" id="email" name="email" type="email" value="{{ old('email') }}" placeholder="{{ __('painel.placeholder_email') }}" required autofocus />

        <x-ui.input label="{{ __('painel.senha') }}" id="password" name="password" type="password" required />

        <x-ui.checkbox name="remember" :label="__('painel.lembrar')" />

        <x-ui.button type="submit" class="w-full">
            {{ __('painel.entrar') }}
        </x-ui.button>
    </form>

    <p class="mt-4 text-center text-sm text-slate-500 dark:text-slate-400">
        {{ __('painel.sem_conta') }}
        <a href="{{ route('register') }}" class="font-semibold text-brand-600 hover:text-brand-700">{{ __('painel.criar_barbearia') }}</a>
    </p>
</div>
