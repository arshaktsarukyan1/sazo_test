FROM node:20-alpine

RUN apk add --no-cache su-exec

WORKDIR /app

COPY infra/docker/frontend-entry.sh /usr/local/bin/frontend-entry
RUN chmod +x /usr/local/bin/frontend-entry

ENTRYPOINT ["/usr/local/bin/frontend-entry"]
CMD ["sh", "-c", "npm install && npm run dev -- --hostname 0.0.0.0 --port 3000"]
