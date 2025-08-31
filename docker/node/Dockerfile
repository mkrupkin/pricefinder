FROM node:18-alpine

WORKDIR /app

# Install dependencies
RUN npm install -g @vue/cli vite

# Copy package files
COPY package*.json ./

# Install npm dependencies
RUN npm install

EXPOSE 3000 5173

CMD ["npm", "run", "dev"]