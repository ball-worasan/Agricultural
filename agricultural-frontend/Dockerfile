## Build stage
FROM node:20-alpine AS builder
RUN apk add --no-cache tzdata
WORKDIR /app
RUN corepack enable

COPY package.json pnpm-lock.yaml ./
RUN --mount=type=cache,id=pnpm-store,target=/root/.local/share/pnpm/store \
    pnpm config set fetch-retries 5 && \
    pnpm config set fetch-retry-factor 3 && \
    pnpm config set network-timeout 600000 && \
    pnpm install --frozen-lockfile

COPY . .
RUN pnpm build

## Runtime stage
FROM node:20-alpine AS runner
RUN apk add --no-cache tzdata
WORKDIR /app
ENV NODE_ENV=production
RUN corepack enable

COPY --from=builder /app/package.json ./package.json
COPY --from=builder /app/node_modules ./node_modules
COPY --from=builder /app/.next ./.next
COPY --from=builder /app/public ./public

EXPOSE 3000

CMD ["pnpm", "start", "-p", "3000"]
