{{--
    Página pública do redirect falhado. Quem chega aqui apontou a câmara a um
    flyer, está de telemóvel na mão e nunca ouviu falar deste produto.

    Slug inexistente e slug inactivo mostram exactamente esta página, com o
    mesmo 404: distingui-los revelaria que aquele endereço já existiu.

    Sem Flux, sem o layout da aplicação, sem navegação, sem sessão e sem
    JavaScript de que o conteúdo dependa.
--}}
<!DOCTYPE html>
<html lang="pt-PT">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex">

    <title>Código não disponível</title>

    {{-- Sem nome do produto no título: quem lê o código não é cliente. --}}

    @fonts

    @vite(['resources/css/app.css'])

    {{-- O tema segue o sistema. Não há sessão para guardar preferência, e sem
         este script a página fica no tema claro — que continua legível. --}}
    <script>
        if (window.matchMedia('(prefers-color-scheme: dark)').matches) {
            document.documentElement.classList.add('dark');
        }
    </script>
</head>

<body class="bg-white dark:bg-zinc-900 font-sans text-zinc-900 antialiased dark:text-zinc-100">
    <main class="flex min-h-screen flex-col items-center justify-center px-6 py-12 text-center">
        <div class="max-w-md">
            <div class="mx-auto grid size-14 place-items-center rounded-card bg-zinc-100 text-zinc-500 dark:bg-zinc-800 dark:text-zinc-400">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="size-7" aria-hidden="true">
                    <rect x="3" y="3" width="7" height="7" rx="1" />
                    <rect x="14" y="3" width="7" height="7" rx="1" />
                    <rect x="3" y="14" width="7" height="7" rx="1" />
                    <path d="M14 14h3v3h-3zM20 14v3M17 20h4" />
                </svg>
            </div>

            <h1 class="mt-5 text-xl font-semibold sm:text-2xl">Este código já não está activo</h1>

            <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-400">
                Quem o publicou desactivou-o ou ainda não o ligou a nenhuma página.
                Não há nada para ver aqui.
            </p>

            <p class="mt-6 text-sm text-zinc-500 dark:text-zinc-400">
                Se veio de um cartaz ou de um flyer, procure aí o endereço do site
                ou o contacto de quem o publicou.
            </p>
        </div>
    </main>
</body>
</html>
