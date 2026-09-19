import csv, random, argparse
def generate(rows, outfile):
    random.seed(42) # Deterministic
    with open(outfile, 'w', newline='') as f:
        w = csv.writer(f)
        w.writerow(['id', 'name', 'email', 'status', 'score'])
        for i in range(rows):
            w.writerow([i, f"User {i}", f"user{i}@example.com", random.choice(['active','inactive']), round(random.random()*100, 2)])
if __name__ == '__main__':
    parser = argparse.ArgumentParser()
    parser.add_argument('--rows', type=int, default=100)
    parser.add_argument('outfile')
    args = parser.parse_args()
    generate(args.rows, args.outfile)
