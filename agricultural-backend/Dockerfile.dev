FROM node:20-alpine
RUN apk add --no-cache tzdata
WORKDIR /app
RUN corepack enable

COPY package.json pnpm-lock.yaml ./

RUN --mount=type=cache,id=pnpm-store,target=/root/.local/share/pnpm/store \
    pnpm config set fetch-retries 5 && \
    pnpm config set fetch-retry-factor 3 && \
    pnpm config set network-timeout 600000 && \
    pnpm install

EXPOSE 3000
