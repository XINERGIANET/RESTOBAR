## Hook de seguridad (detección del implante "A8-3424-1")

Este repo fue infectado por una campaña de malware que inyecta código ofuscado en archivos
de configuración de build (`vite.config.js`, `webpack.mix.js`, `postcss.config.js`, etc.) y
en `.vscode/tasks.json`. Detalle e historial: preguntar a Falu.

Para activar la protección localmente (una sola vez por clon):

```
git config core.hooksPath .githooks
```

A partir de ahí, tres hooks quedan activos:

| Hook | Cuándo corre | ¿Bloquea? |
|---|---|---|
| `pre-commit` | Antes de cada commit | **Sí** — aborta el commit si hay una firma conocida, una línea anormalmente larga en un config de build, o una tarea `runOn:folderOpen` |
| `post-merge` | Después de `git pull` | No — git no permite bloquear un fast-forward antes de que los archivos toquen disco. Solo alerta en la terminal |
| `post-checkout` | Después de `git clone` / `git checkout <rama>` | No, mismo motivo que arriba. Solo alerta |

También corre en GitHub Actions en cada push como respaldo server-side (cubre a quien
comitea sin el hook local instalado, o lo salta con `--no-verify`).

Si estás seguro de que un hallazgo es un falso positivo, `git commit --no-verify` salta el
`pre-commit` (no recomendado sin revisar primero).
