# 3.0.0

#### Changes

* Requires PHP 8.0
* `DockerComposeLocator` interface changed
* `DockerfileCompiler` constructor signature changed

#### New features

* Added app env functionality

# 2.0.0

#### Changes

* Removed `absolutePath` mechanism for dealing with symlinked contexts outside main building context and implemented
  copying context files instead.

### 1.0.1

#### Bugfixes

* `docker-compose.yml` aggregation was losing `build` subkeys (i.e. `args`)
* Crash on uninitialized `build` value