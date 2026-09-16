<nav x-data="{ open: false }" class="border-b border-gray-200 bg-white">
    <div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">
        <div class="flex h-16 justify-between">

            <div class="flex items-center gap-8">
                <a href="{{ route('home') }}" class="flex-shrink-0" aria-label="Página inicial da FEMOPROR">
                    <picture>
                        <source srcset="{{ asset('images/topo.webp') }}" type="image/webp">
                        <img src="{{ asset('images/topo.png') }}" alt="FEMOPROR" width="400" height="133" class="h-8 w-auto">
                    </picture>
                </a>

                <div class="hidden items-center gap-1 sm:flex">
                    <x-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')">
                        Minhas inscrições
                    </x-nav-link>

                    <x-nav-link :href="route('profile.edit')" :active="request()->routeIs('profile.edit')">
                        Meu perfil
                    </x-nav-link>
                </div>
            </div>

            <div class="hidden items-center gap-3 sm:flex">
                <a href="{{ route('home') }}" class="text-sm text-gray-500 transition-colors hover:text-green-900">
                    Voltar para o site
                </a>

                <span class="h-4 w-px bg-gray-200" aria-hidden="true"></span>

                <x-dropdown align="right" width="48">
                    <x-slot name="trigger">
                        <button class="inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1.5 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-50">
                            {{ explode(' ', Auth::user()->name)[0] }}
                            <svg class="h-3.5 w-3.5 text-gray-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>
                    </x-slot>

                    <x-slot name="content">
                        <div class="border-b border-gray-100 px-4 py-3">
                            <p class="truncate text-sm font-medium text-gray-900">{{ Auth::user()->name }}</p>
                            <p class="truncate text-xs text-gray-500">{{ Auth::user()->email }}</p>
                        </div>

                        <x-dropdown-link :href="route('profile.edit')">Meu perfil</x-dropdown-link>

                        @if(Auth::user()->isChurchPresident())
                            <x-dropdown-link href="{{ url('/ump') }}">Área da UMP</x-dropdown-link>
                        @endif

                        @if(Auth::user()->isAdmin())
                            <x-dropdown-link href="{{ url('/area-da-diretoria') }}">Área da Diretoria</x-dropdown-link>
                        @endif

                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <x-dropdown-link :href="route('logout')"
                                    onclick="event.preventDefault(); this.closest('form').submit();">
                                Sair
                            </x-dropdown-link>
                        </form>
                    </x-slot>
                </x-dropdown>
            </div>

            <div class="-me-2 flex items-center sm:hidden">
                <button @click="open = ! open" :aria-expanded="open"
                        class="inline-flex items-center justify-center rounded-lg p-2 text-gray-500 transition hover:bg-gray-100">
                    <span class="sr-only">Abrir menu</span>
                    <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                        <path :class="{'hidden': open }" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        <path :class="{'hidden': ! open }" class="hidden" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <div x-show="open" x-cloak class="border-t border-gray-200 sm:hidden">
        <div class="space-y-1 px-3 py-3">
            <x-responsive-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')">
                Minhas inscrições
            </x-responsive-nav-link>

            <x-responsive-nav-link :href="route('profile.edit')" :active="request()->routeIs('profile.edit')">
                Meu perfil
            </x-responsive-nav-link>

            @if(Auth::user()->isChurchPresident())
                <x-responsive-nav-link href="{{ url('/ump') }}">Área da UMP</x-responsive-nav-link>
            @endif

            @if(Auth::user()->isAdmin())
                <x-responsive-nav-link href="{{ url('/area-da-diretoria') }}">Área da Diretoria</x-responsive-nav-link>
            @endif

            <x-responsive-nav-link :href="route('home')">Voltar para o site</x-responsive-nav-link>
        </div>

        <div class="border-t border-gray-200 px-3 py-3">
            <div class="px-3 pb-2">
                <p class="text-sm font-medium text-gray-900">{{ Auth::user()->name }}</p>
                <p class="text-xs text-gray-500">{{ Auth::user()->email }}</p>
            </div>

            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <x-responsive-nav-link :href="route('logout')"
                        onclick="event.preventDefault(); this.closest('form').submit();">
                    Sair
                </x-responsive-nav-link>
            </form>
        </div>
    </div>
</nav>
