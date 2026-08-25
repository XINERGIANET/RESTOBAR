## Hook de seguridad (detección del implante "A8-3424-1")

Este repo fue infectado por una campaña de malware que inyecta código ofuscado en archivos
de configuración de build (`vite.config.js`, `webpack.mix.js`, `postcss.config.js`, etc.) y
en `.vscode/tasks.json`. Detalle e historial: preguntar a Falu.

Para activar la protección localmente (una sola vez por clon):

```
git config core.hooksPath .githooks
```

A partir de ahí, cualquier intento de commitear un archivo con la firma conocida del
implante, o con una línea anormalmente larga en un config de build, se bloqueará
automáticamente. También corre en GitHub Actions en cada push como respaldo.
