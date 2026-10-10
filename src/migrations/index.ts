import * as migration_20261010_032857_initial from './20261010_032857_initial'

export const migrations = [
  {
    up: migration_20261010_032857_initial.up,
    down: migration_20261010_032857_initial.down,
    name: '20261010_032857_initial',
  },
]
