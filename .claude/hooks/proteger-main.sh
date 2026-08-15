#!/usr/bin/env bash
# Impede commits e pushes diretos na main/master.
#
# Enforcement real: não depende de o Claude se lembrar da regra.
# Complementa o ruleset do GitHub, que protege o lado remoto.
#
# ⚠️ Hooks de .claude/settings.json só correm depois de aceitar o diálogo de
#    confiança da pasta (workspace trust). Confirmar no primeiro arranque.

set -uo pipefail

# Falhar de forma consciente se o jq não existir, em vez de morrer a meio.
if ! command -v jq >/dev/null 2>&1; then
  echo "hook proteger-main: jq não encontrado; verificação de branch ignorada" >&2
  exit 0
fi

INPUT=$(cat)
COMMAND=$(echo "$INPUT" | jq -r '.tool_input.command // ""')

# Remover comentários (tudo depois de #) para não disparar com
# "git status  # fazer git commit depois"
COMMAND_SEM_COMENTARIOS=${COMMAND%%#*}

# Apanha: git commit, git push, e também git -C <path> / --git-dir / --work-tree
if ! echo "$COMMAND_SEM_COMENTARIOS" | grep -qE '(^|[;&|]|[[:space:]])git([[:space:]]+(-C[[:space:]]+[^[:space:]]+|--git-dir=[^[:space:]]+|--work-tree=[^[:space:]]+))*[[:space:]]+(commit|push)([[:space:]]|$)'; then
  exit 0
fi

REPO_DIR="${CLAUDE_PROJECT_DIR:-.}"
BRANCH=$(git -C "$REPO_DIR" branch --show-current 2>/dev/null || echo "")

if [ "$BRANCH" = "main" ] || [ "$BRANCH" = "master" ]; then
  jq -n --arg b "$BRANCH" '{
    hookSpecificOutput: {
      hookEventName: "PreToolUse",
      permissionDecision: "deny",
      permissionDecisionReason: ("Estás na branch \($b). O fluxo do projeto exige uma branch por issue. Cria uma com: git checkout -b <tipo>/<nº>-<descricao>")
    }
  }'
fi

exit 0
