CREATE TABLE products (
    id INTEGER PRIMARY KEY,
    name TEXT NOT NULL,
    handle TEXT NOT NULL UNIQUE,
    body_html TEXT,
    price REAL NOT NULL,
    compare_at_price REAL,
    category TEXT,
    in_stock BOOLEAN DEFAULT TRUE,
    rating REAL,
    review_count INTEGER,
    tags TEXT,
    vendor TEXT,
    bestseller_score REAL,
    raw_json TEXT
);

CREATE TABLE product_images (
    id INTEGER PRIMARY KEY,
    product_id INTEGER NOT NULL,
    position INTEGER,
    src TEXT NOT NULL,
    width INTEGER,
    height INTEGER,
    created_at TEXT,
    updated_at TEXT,
    FOREIGN KEY (product_id) REFERENCES products (id)
);

CREATE VIRTUAL TABLE products_fts USING fts5(
    name,
    handle,
    body_html,
    category,
    tags,
    vendor,
    content='products',
    content_rowid='id'
);
