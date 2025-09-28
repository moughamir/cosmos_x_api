# Integrating the Products API with a Next.js Frontend

This document provides a guide on how to integrate the Products API with a Next.js application, using server-side rendering (SSR) for optimal performance and SEO.

For detailed information about the API endpoints, see the [API Documentation](API_DOCUMENTATION.md).

## 1. Prerequisites

- A running instance of the Products API.
- A Next.js project.
- The API key for the Products API.

## 2. Setting up the Next.js Project

### Environment Variables

It is recommended to store the API URL and API key in an `.env.local` file in the root of your Next.js project. This keeps your sensitive information out of your source code.

```
NEXT_PUBLIC_API_URL=http://your-api-domain.com/cosmos
API_KEY=YOUR_SECRET_API_KEY
```

## 3. Fetching a List of Products (Server-Side Rendering)

To fetch a list of products on the server-side, you can use the `getServerSideProps` function in your Next.js page.

Here is an example of a page that fetches and displays a list of products:

```javascript
// pages/products/index.js

import React from 'react';

const ProductsPage = ({ products }) => {
  return (
    <div>
      <h1>Products</h1>
      <ul>
        {products.map((product) => (
          <li key={product.id}>
            <a href={`/products/${product.handle}`}>{product.name}</a>
            <p>{product.price}</p>
          </li>
        ))}
      </ul>
    </div>
  );
};

export async function getServerSideProps() {
  const apiUrl = process.env.NEXT_PUBLIC_API_URL;
  const apiKey = process.env.API_KEY;

  const response = await fetch(`${apiUrl}/products`, {
    headers: {
      'X-API-KEY': apiKey,
    },
  });

  const data = await response.json();

  return {
    props: {
      products: data.products,
    },
  };
}

export default ProductsPage;
```

## 4. Fetching a Single Product (Server-Side Rendering)

To fetch a single product on the server-side, you can use the `getServerSideProps` function with a dynamic route.

Here is an example of a page that fetches and displays a single product:

```javascript
// pages/products/[handle].js

import React from 'react';

const ProductPage = ({ product }) => {
  if (!product) {
    return <div>Product not found</div>;
  }

  return (
    <div>
      <h1>{product.name}</h1>
      <p>{product.body_html}</p>
      <p>Price: {product.price}</p>
      <div>
        {product.images.map((image) => (
          <img key={image.id} src={image.src} alt={product.name} width={image.width} height={image.height} />
        ))}
      </div>
    </div>
  );
};

export async function getServerSideProps(context) {
  const { handle } = context.params;
  const apiUrl = process.env.NEXT_PUBLIC_API_URL;
  const apiKey = process.env.API_KEY;

  const response = await fetch(`${apiUrl}/products/${handle}`, {
    headers: {
      'X-API-KEY': apiKey,
    },
  });

  const data = await response.json();

  return {
    props: {
      product: data.product,
    },
  };
}

export default ProductPage;
```

## 5. Conclusion

This guide provides a basic example of how to integrate the Products API with a Next.js application. You can extend this example to include features like pagination, search, and filtering.