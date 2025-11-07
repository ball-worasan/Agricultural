# AI Agent Instructions for Agricultural Project

## Project Overview
This is a monorepo containing a full-stack agricultural system with:
- Backend: NestJS + MongoDB (port 4000)
- Frontend: Next.js + Material UI (port 3000)
- Docker Compose setup for development

## Architecture and Key Patterns

### Backend (NestJS)
- **Authentication Flow**: JWT-based auth implemented in `/auth` module with registration/login endpoints
- **Module Structure**: Each feature is a self-contained module (e.g., `users`, `auth`)
- **DTOs Pattern**: Input validation using class-validator DTOs in `dto/` folders
- **Configuration**: Environment variables handled through `config/configuration.ts`
- **Error Handling**: Global HTTP exception filter in `common/filters/http-exception.filter.ts`

### Frontend (Next.js)
- **App Router**: Uses Next.js 13+ app directory structure
- **Theme**: Material UI with custom theme in `app/theme.ts`
- **Layout**: Base layout with header/footer components
- **Authentication**: Protected routes handled in `middleware.ts`
- **Dynamic Routes**: Uses Next.js dynamic segments (e.g., `[id]` folders)

## Development Workflow

### Local Development
```bash
# Start all services (recommended)
docker compose up

# Start individual services
docker compose up backend
docker compose up frontend
docker compose up mongodb
```

### Key Files for Common Tasks
- Adding new API endpoint: Create controller in relevant module (see `users.controller.ts`)
- Adding new frontend page: Create page.tsx in app directory (see `app/listing/[id]/page.tsx`)
- Database schema changes: Update relevant schema in `schemas/` folder
- Environment configuration: Check `.env` files and `config/configuration.ts`

## Project-Specific Conventions

### Backend
- All DTOs use class-validator decorators for validation
- MongoDB schemas defined using `@nestjs/mongoose` decorators
- Use interceptors for cross-cutting concerns (see `timeout.interceptor.ts`)

### Frontend
- Page components go in `app/` directory
- Reusable components go in `components/` directory
- Custom hooks go in `hooks/` directory
- Material UI components used for consistency

## Integration Points
- Backend API base URL: http://localhost:4000
- MongoDB connection: mongodb://root:example@localhost:27017
- Frontend-Backend communication through Axios/Fetch API
- Authentication via JWT tokens in Authorization header

## Common Gotchas
- Docker volumes for node_modules to avoid host OS conflicts
- MongoDB requires authentication (root/example in dev)
- Backend serves Swagger UI at /api
- Frontend environment variables must be prefixed with `NEXT_PUBLIC_`

## Reference Examples
- Authentication flow: See `auth.controller.ts` and `login/page.tsx`
- Protected routes: See `middleware.ts` and `jwt-auth.guard.ts`
- Data validation: See `create-user.dto.ts` for backend, form validation in frontend
- Error handling: See `http-exception.filter.ts` and how errors are propagated to frontend