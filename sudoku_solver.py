#!/usr/bin/python
import copy
import random
import math
import string 

test = "-3--8---65--29471----3--5----5-1-8-442-8-5-391-8-3-6----3--7----41653--22---4--6-"
test2 = "1--4--78-45-7----37--12-4----4-67-9--678-1-3-891-34--7--56-891--78----4---23---7-"
medium1 = "3.8296....4...8...5.21...87.13......78.....35......41.12...78.3...8...2....5421.6"
hard1 = "7........6..41.25..13.95...86.......3.1...4.5.......86...84.53..42.36..7........9"
evil1 = ".6.8.......4.6...91...43.6..52........86.93........57..1.48...58...1.2.......5.4."
new = "1----------23---74---2---856--87--5----9-6----9--24--358---2---94---78----------6"

sudoku_n_length = 9
queue_length = 0 

def to_periods(board):
    return board.replace('.', '-')

def draw_board(sudoku_string):
	#Function: converts sudoku string into 9x9 matrix 
	#Input: string 
	#Output: the 9x9 matrix 
	board = [[sudoku_string[x+(sudoku_n_length*y)] for x in range(sudoku_n_length)] for y in range(sudoku_n_length)]
	return board

def printboard(board):
	#Function: prints 9x9 matrix into pretty sudoku board
	#Input: 9x9 matrix
	#Output: prints the matrix 
	border = '\n|-----------|-----------|-----------|'
	large_border = '\n|===========|===========|===========|'
	for x in range(sudoku_n_length):
		if (x%3 == 0):
			print(large_border)
		else: 
			print(border)
		for y in range(sudoku_n_length):
			print("| ", end="")
			if (board[x][y] != '-'):
				print(board[x][y], end="")
			else:
				print(" ", end="")
			print(" ", end="")
			if (y%8==0 and y!=0):
				print("|", end="")
	print(large_border)
	
def done(board):
	#Function: Returns true if puzzle is solved, false if not
	#Input: the sudoku 9x9 matrix
	#Output: true if puzzle is solved, false if not
	for rows in board:
		for columns in rows:
			if (columns == '-'):
				return False 
	return True 

def concat(list_of_lists):
    lis = []
    for l in list_of_lists:
        lis += l
    return lis

def isvalid(board):
    #Function: Checks if putting a valid in a specific spot on the board is valid
    #Input: 
    #   board: the sudoku 9x9 matrix
    #Output: true if valid move, false if not valid
    cols = [[] for i in range(9)]
    rows = board[:]
    squares = [[] for i in range(9)]

    for i in range(9):
        for j in range(9):
            cols[i].append(board[j][i])

    top_lefts = concat([[[i,j] for i in range(9) if i % 3 == 0] for j in range(9) if j % 3 == 0])
    relative_coords = concat([[[i,j] for i in range(3)] for j in range(3)])

    squares = []
    for i,j in top_lefts:
        square = []
        for x,y in relative_coords:
            square.append(board[i+x][j+y])
        squares.append(square)

    constraints = concat([cols,rows,squares])
    return all(map(check, constraints))

def check(elements):
    not_empty = [i for i in elements if i != '-']
    if len(list(set(not_empty))) == len(not_empty):
        return True
    else:
        return False 

def findspot(board):
    #Function: Finds an open spot on the board randomly 
    #Input: the sudoku 9x9 matrix
    #Output: random spot on board
    for spot in range(81):
        if (board[math.floor(spot/9)][spot%9] == "-"):
            return spot
def get_arcs(board):
	#Output: a board with csp's satisfied
	spot = findspot(board)
	return [board,spot]

def ac3(board):
    #Function:Finds arc consistent domains for each variable.
    #Output: Arc consistent domains for each variable
    #Initial domains are made consistent with unary constraints.
    #A set of domains D(x) for each variable {1,...9} , 1 =< x =< 9domain = [1,2,3,4,5,6,7,8,9]
    queue = [get_arcs(board)]
    while (queue):
        [board,spot] = queue.pop(0)
        worklist = revise(board,spot)
        for w in worklist:
            if done(w) and isvalid(w):
                return True, w
            else:
                queue.append(get_arcs(w))
    return False, None

def revise(board, spot):
    #Function: Returns true if we refine the domain of 
    #Input: Finds arc consistent domains for each variable.
    #Output: Returns false if an inconsistency is found, true otherwise (if we remove a value)
    domain =[str(i) for i in range(1,10)]
    row = spot//9
    col = spot%9
    worklist = []
    global queue_length

    for x in domain:
        possibilities = copy.deepcopy(board)
        possibilities[row][col] = x
        if isvalid(possibilities):
            if (queue_length%1000 ==0):
                print(queue_length)
            worklist.append(possibilities)
            queue_length += 1 
    return worklist

def main(test):
    board = draw_board(test)
    is_sol, board = ac3(board)
    if is_sol:
        printboard(board)
    else:
        print(is_sol)

main(to_periods(new))