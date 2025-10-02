```kanban

---

- ### backlog
  - [ ] **Epic: Performance Optimization** (Priority: Medium) (Due: TBD)
    - [ ] Implement Redis caching layer for common queries and API responses.
    - [ ] Optimize database queries and add missing indexes.
    - [ ] Investigate read replicas for the database.
  - [ ] **Epic: Deployment and Infrastructure** (Priority: Medium) (Due: TBD)
    - [ ] Enhance Docker setup for production readiness.
    - [ ] Create a CI/CD pipeline for automated testing and deployment.
    - [ ] Implement monitoring and logging for the production environment.
    - [ ] Configure auto-scaling for the application.
  - [ ] **Epic: Documentation** (Priority: Low) (Due: TBD)
    - [ ] Create a comprehensive deployment guide.
    - [ ] Write a development setup guide.

- ### todo
  - [ ] **Testing: Write Validation Tests** (Priority: Medium) (Due: TBD)
    - Create unit tests for the validation rules.
  - [ ] **Testing: Write Integration Tests** (Priority: Medium) (Due: TBD)
    - *Depends on: All major bugs being fixed.*
    - Create integration tests for the API endpoints.

- ### wip
  - [ ] **Testing: Write Unit Tests for Services** (Priority: Medium) (Due: TBD)
    - *Depends on: Bug fixes in services.*
    - Create unit tests for `ProductService`, `ImageService`, `SimilarityService`, and `HealthCheckService`.

- ### done
  - [x] **Testing: Write Unit Tests for Controllers** (Priority: Medium)
    - *Depends on: Bug fixes in controllers.*
    - Create unit tests for `ApiController`, `DocsController`, and `HealthController`.
- ### done
  - [x] **Feature: Input Validation** (Priority: High)
    - [x] Fix `msgpack_pack` function handling.
    - [x] Fix unassigned `$params` variable.
  - [x] **Bug: Fix Undefined Variable in `getProducts`** (Priority: High)
    - In `src/Controllers/ApiController.php`, replaced `$params` with `$queryParams`.
  - [x] **Bug: Implement `getPrecomputedRelated` Method** (Priority: High)
    - Added the `getPrecomputedRelated` method to `src/Services/SimilarityService.php`.
  - [x] **Security: Secure `ImageProxy`** (Priority: High)
    - Implemented a strict allowlist of trusted domains in `src/Services/ImageProxy.php`.
    - Removed `verify => false` from the Guzzle client.
  - [x] **Bug: Add Container to `ApiController`** (Priority: High)
    - Injected the DI container into `ApiController`'s constructor and assigned it to a property.
  - [x] **Security: Remove Hardcoded API Key** (Priority: High)
    - Removed the hardcoded API key from `docker-compose.yml` and updated documentation to use environment variables.
  - [x] **Chore: Remove Error Suppression** (Priority: Medium)
    - Removed error suppression from `bin/generate_openapi.php`.
  - [x] **Chore: Fix `ImageProxy` Method Signature** (Priority: Medium)
    - Updated the `output` method in `src/Services/ImageProxy.php` to get the URL from query parameters.
  - [x] **Chore: Fix Duplicate OpenAPI Annotation** (Priority: Low)
    - Removed the duplicate `@OA\Parameter` for `format` in `src/Controllers/ApiController.php`.
  - [x] **Feature: OpenAPI Documentation**
    - [x] Set up comprehensive OpenAPI specification.
    - [x] Implemented Swagger UI.
    - [x] Added JSON endpoint for OpenAPI spec.
    - [x] Documented all major endpoints.
  - [x] **Feature: Basic Validation System**
    - [x] Created validation rules for required, length, numeric, email, and in.
    - [x] Implemented `ValidationMiddleware`.
    - [x] Added `ValidatesRequests` trait.

- ### review

- ### done done

---

```