import { copyFileSync, mkdirSync } from 'node:fs'
import { dirname, resolve } from 'node:path'
import { fileURLToPath } from 'node:url'

// `maizzle build production` empties resources/views/mail before writing the
// compiled templates. Blade partials live next to them, so copy ours back in.
const root = dirname(fileURLToPath(import.meta.url))
const source = resolve(root, 'partials/operator-footer.blade.php')
const destination = resolve(root, '../resources/views/mail/partials/operator-footer.blade.php')

mkdirSync(dirname(destination), { recursive: true })
copyFileSync(source, destination)
