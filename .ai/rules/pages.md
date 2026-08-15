---
paths:
  - 'resources/views/pages/**'
---

# Pages

## Nada de `use` de classes globais nos componentes Livewire SFC
Num componente de ficheiro único (`resources/views/pages/**/⚡*.blade.php`), um `use Throwable;` — ou qualquer `use` de classe sem namespace — rebenta a página com "The use statement with non-compound name has no effect", porque o Livewire compila o bloco PHP para dentro de uma classe já namespaced e o aviso do PHP vira ErrorException.

Escrever `\Throwable` (ou `\DateTimeInterface`, etc.) directamente no sítio onde se usa. Classes com namespace importam-se normalmente.
